<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Tests\Benchmark;

use Vos\LabelClassifier\Benchmark\BenchmarkCase;
use Vos\LabelClassifier\Benchmark\BenchmarkOutcome;
use Vos\LabelClassifier\Benchmark\BenchmarkReport;
use Vos\LabelClassifier\Benchmark\LabelAliases;
use Vos\LabelClassifier\Context\ContextSources;
use Vos\LabelClassifier\Context\PullRequestContext;
use Vos\LabelClassifier\Context\PullRequestData;
use Vos\LabelClassifier\Evaluation\Evaluation;
use Vos\LabelClassifier\Evaluation\EvaluationMetadata;
use Vos\LabelClassifier\Evaluation\LabelDecisions;
use Vos\LabelClassifier\Evaluation\LabelProbabilities;
use Vos\LabelClassifier\LabelingOutcome;
use Vos\LabelClassifier\Policy\LabelApplication;
use Vos\LabelClassifier\Policy\LabelPolicy;
use Vos\LabelClassifier\Policy\PolicyConfiguration;
use Vos\LabelClassifier\Policy\Probability;
use PHPUnit\Framework\TestCase;

final class BenchmarkReportTest extends TestCase
{
    public function testPrecisionAndRecallAreCountedPerLabel(): void
    {
        $report = BenchmarkReport::fromOutcomes([
            $this->outcome('owner/repo#1', observed: ['Bug'], probabilities: ['Bug' => 0.9]),
            $this->outcome('owner/repo#2', observed: ['Bug'], probabilities: ['Feature' => 0.9]),
            $this->outcome('owner/repo#3', observed: ['Bug'], probabilities: ['Bug' => 0.77]),
        ], LabelAliases::fromArray([]));

        $metrics = $report->metrics();

        self::assertSame(3, $report->caseCount());
        self::assertSame(1, $metrics['Bug']->truePositives());
        self::assertSame(0, $metrics['Bug']->falsePositives());
        self::assertSame(2, $metrics['Bug']->falseNegatives());
        self::assertSame(1.0, $metrics['Bug']->precision());
        self::assertSame(1 / 3, $metrics['Bug']->recall());
        self::assertSame(1, $metrics['Bug']->abstainedCount());
        self::assertNull($metrics['Feature']->recall());
        self::assertSame(0.0, $metrics['Feature']->precision());
    }

    public function testExpectedLabelsAreNormalizedThroughTheAliasMap(): void
    {
        $report = BenchmarkReport::fromOutcomes([
            $this->outcome('owner/repo#1', observed: ['Bugfix'], probabilities: ['Bug' => 0.9]),
        ], LabelAliases::fromArray(['Bugfix' => 'Bug']));

        $metrics = $report->metrics();

        self::assertSame(1, $metrics['Bug']->truePositives());
        self::assertArrayNotHasKey('Bugfix', $metrics);
    }

    public function testUsageAndLatencyAreSummed(): void
    {
        $report = BenchmarkReport::fromOutcomes([
            $this->outcome('owner/repo#1', observed: [], probabilities: ['Bug' => 0.9], usage: ['prompt_tokens' => 10]),
            $this->outcome('owner/repo#2', observed: [], probabilities: ['Bug' => 0.1], usage: ['prompt_tokens' => 15]),
        ], LabelAliases::fromArray([]));

        self::assertSame(['prompt_tokens' => 25], $report->usageTotals());
        self::assertGreaterThan(0.0, $report->totalLatencyMilliseconds());
    }

    /** @param list<string> $observed
     *  @param array<string, float> $probabilities
     *  @param array<string, int|float> $usage
     */
    private function outcome(string $identifier, array $observed, array $probabilities, array $usage = ['prompt_tokens' => 1]): BenchmarkOutcome
    {
        $policies = PolicyConfiguration::fromPolicies([
            new LabelPolicy('Bug', 'q', Probability::fromFloat(0.8), LabelApplication::Auto),
            new LabelPolicy('Feature', 'q', Probability::fromFloat(0.8), LabelApplication::Auto),
        ]);

        $labeling = new LabelingOutcome(
            PullRequestContext::fromData(PullRequestData::empty(), ContextSources::default()),
            new Evaluation(LabelProbabilities::fromMap($probabilities), new EvaluationMetadata('m', $usage)),
            LabelDecisions::from($policies, LabelProbabilities::fromMap($probabilities), Probability::fromFloat(0.05)),
        );

        return new BenchmarkOutcome(
            new BenchmarkCase($identifier, PullRequestData::empty(), $observed, [], null, null, null, null),
            $labeling,
            1.5,
        );
    }
}
