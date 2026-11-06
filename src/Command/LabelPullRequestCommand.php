<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Command;

use Vos\LabelClassifier\Context\PullRequestData;
use Vos\LabelClassifier\Context\ContextSources;
use Vos\LabelClassifier\Evaluation\LabelDecision;
use Vos\LabelClassifier\GitHub\DocumentationPaths;
use Vos\LabelClassifier\GitHub\GitHubHttp;
use Vos\LabelClassifier\GitHub\GitHubPullRequestData;
use Vos\LabelClassifier\GitHub\RepositoryName;
use Vos\LabelClassifier\Infrastructure\Jev\JevClient;
use Vos\LabelClassifier\Infrastructure\Jev\JevEvaluator;
use Vos\LabelClassifier\LabelingOutcome;
use Vos\LabelClassifier\PullRequestLabeler;
use Vos\LabelClassifier\Policy\PolicyConfiguration;
use Vos\LabelClassifier\Policy\Probability;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'label-pull-request',
    description: 'Labels a pull request by answering the label policies against its context',
)]
final class LabelPullRequestCommand extends Command
{
    private const DOCUMENTATION_PATHS_DEFAULT = 'README.md,UPGRADE.md,docs/en/index.rst';

    protected function configure(): void
    {
        $this
            ->addOption(
                'repository',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Repository in owner/name form',
                default: getenv('GITHUB_REPOSITORY') ?: null,
            )
            ->addOption(
                'pr',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Pull request number',
                default: null,
            )
            ->addOption(
                'event-path',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Path to the GitHub event payload',
                default: getenv('GITHUB_EVENT_PATH') ?: null,
            )
            ->addOption(
                'api-url',
                mode: InputOption::VALUE_REQUIRED,
                description: 'GitHub API base URL',
                default: getenv('GITHUB_API_URL') ?: 'https://api.github.com',
            )
            ->addOption(
                'labels-config',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Path to the policy configuration',
                default: dirname(__DIR__, 2) . '/config/labels.json',
            )
            ->addOption(
                'thresholds',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Per-label threshold overrides, e.g. "Bug=0.9,Feature=0.7"',
                default: getenv('LABEL_THRESHOLDS') ?: '',
            )
            ->addOption(
                'abstain-margin',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Band below a threshold that counts as abstention instead of rejection',
                default: getenv('LABEL_ABSTAIN_MARGIN') ?: '0.05',
            )
            ->addOption(
                'model',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Jev model identifier',
                default: getenv('JEV_MODEL') ?: 'jev-1.13',
            )
            ->addOption(
                'jev-endpoint',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Jev API endpoint, for example an OpenCode gateway',
                default: getenv('JEV_ENDPOINT') ?: JevClient::DEFAULT_ENDPOINT,
            )
            ->addOption(
                'docs-paths',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Comma-separated documentation paths loaded from the base revision',
                default: getenv('LABEL_DOCS_PATHS') ?: self::DOCUMENTATION_PATHS_DEFAULT,
            )
            ->addOption(
                'input',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Evaluate frozen pull request data from a JSON file instead of the GitHub API',
                default: null,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $labeler = $this->labeler($input);

        $frozenInput = (string) $input->getOption('input');

        if ($frozenInput !== '') {
            return $this->evaluateFrozen($frozenInput, $labeler, $output);
        }

        return $this->suggestFromGitHub($input, $labeler, $output);
    }

    /**
     * The command's only job in a workflow: suggest labels. Applying them is
     * the caller's decision, so the suggestions are also written in GitHub
     * Actions output format when GITHUB_OUTPUT is available.
     */
    private function suggestFromGitHub(InputInterface $input, PullRequestLabeler $labeler, OutputInterface $output): int
    {
        $repository = RepositoryName::fromString($this->requiredStringOption($input, 'repository'));
        $number = $this->pullRequestNumber($input);
        $http = new GitHubHttp(HttpClient::create(), (string) $input->getOption('api-url'), $this->githubToken());

        $data = (new GitHubPullRequestData($http, $repository, $number, $this->documentationPaths($input)))->fetch();
        $outcome = $labeler->label($data);

        $this->renderOutcome($outcome, $output);

        $this->writeSuggestions($outcome->decisions->selectedLabels(), $outcome->decisions->advisoryLabels());

        return Command::SUCCESS;
    }

    /** @param list<string> $labels
     *  @param list<string> $advisory
     */
    private function writeSuggestions(array $labels, array $advisory): void
    {
        $githubOutput = getenv('GITHUB_OUTPUT');

        if (! is_string($githubOutput) || $githubOutput === '') {
            return;
        }

        $bytes = file_put_contents($githubOutput, sprintf(
            "labels=%s\nadvisory=%s\n",
            implode(',', $labels),
            implode(',', $advisory),
        ), FILE_APPEND);

        if ($bytes === false) {
            throw new RuntimeException(sprintf('Unable to write suggestions to %s.', $githubOutput));
        }
    }

    private function evaluateFrozen(string $path, PullRequestLabeler $labeler, OutputInterface $output): int
    {
        $this->renderOutcome($labeler->label(PullRequestData::fromFile($path)), $output);

        return Command::SUCCESS;
    }

    private function labeler(InputInterface $input): PullRequestLabeler
    {
        $policies = PolicyConfiguration::fromFile(
            $this->requiredStringOption($input, 'labels-config'),
            (string) $input->getOption('thresholds'),
        );

        return new PullRequestLabeler(
            evaluator: new JevEvaluator(
                client: new JevClient(
                    http: HttpClient::create(),
                    apiKey: $this->jevApiKey(),
                    model: $this->requiredStringOption($input, 'model'),
                    endpoint: (string) $input->getOption('jev-endpoint'),
                ),
                policies: $policies,
            ),
            policies: $policies,
            sources: ContextSources::default(),
            abstainMargin: Probability::fromString((string) $input->getOption('abstain-margin')),
        );
    }

    private function renderOutcome(LabelingOutcome $outcome, OutputInterface $output): void
    {
        $output->writeln('Suggested labels:');

        foreach ($outcome->decisions->all() as $decision) {
            $output->writeln(sprintf(
                '  %-17s %-9s %s',
                $decision->policy->label,
                $decision->kind->value,
                $this->probabilityText($decision),
            ));
        }

        $output->writeln('Context: ' . $outcome->context->completeness());

        $model = $outcome->evaluation->metadata->model;

        if ($model !== null) {
            $output->writeln('Resolved model: ' . $model);
        }
    }

    private function probabilityText(LabelDecision $decision): string
    {
        if ($decision->probability === null) {
            return 'no answer';
        }

        return sprintf('%.2f', $decision->probability);
    }

    private function documentationPaths(InputInterface $input): DocumentationPaths
    {
        return DocumentationPaths::fromCommaSeparated((string) $input->getOption('docs-paths'));
    }

    private function pullRequestNumber(InputInterface $input): int
    {
        $option = (string) $input->getOption('pr');

        if ($option !== '') {
            if (ctype_digit($option)) {
                return (int) $option;
            }

            throw new RuntimeException('--pr must be a pull request number.');
        }

        $eventPath = (string) $input->getOption('event-path');

        if ($eventPath !== '') {
            return $this->numberFromEvent($eventPath);
        }

        throw new RuntimeException('No pull request number. Use --pr or --event-path.');
    }

    private function numberFromEvent(string $path): int
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read event payload at %s.', $path));
        }

        $payload = json_decode($contents, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        $number = is_array($payload) ? ($payload['pull_request']['number'] ?? null) : null;

        if (! is_int($number)) {
            throw new RuntimeException('The event payload does not carry a pull request number.');
        }

        return $number;
    }

    private function githubToken(): string
    {
        return $this->environmentValue('GITHUB_TOKEN');
    }

    private function jevApiKey(): string
    {
        return $this->environmentValue('JEV_API_KEY');
    }

    private function environmentValue(string $name): string
    {
        $value = getenv($name);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('%s is not set.', $name));
        }

        return $value;
    }

    private function requiredStringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('The --%s option is required.', $name));
        }

        return $value;
    }
}
