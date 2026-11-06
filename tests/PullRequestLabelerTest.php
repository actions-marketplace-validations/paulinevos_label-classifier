<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Tests;

use Vos\LabelClassifier\Context\ContextSources;
use Vos\LabelClassifier\Context\PullRequestContext;
use Vos\LabelClassifier\Context\PullRequestData;
use Vos\LabelClassifier\Evaluation\Evaluation;
use Vos\LabelClassifier\Evaluation\EvaluationMetadata;
use Vos\LabelClassifier\Evaluation\Evaluator;
use Vos\LabelClassifier\Evaluation\LabelProbabilities;
use Vos\LabelClassifier\Policy\LabelApplication;
use Vos\LabelClassifier\Policy\LabelPolicy;
use Vos\LabelClassifier\Policy\PolicyConfiguration;
use Vos\LabelClassifier\Policy\Probability;
use Vos\LabelClassifier\PullRequestLabeler;
use PHPUnit\Framework\TestCase;

final class PullRequestLabelerTest extends TestCase
{
    public function testTheLabelerFeedsOneContextToTheEvaluatorAndCombinesThresholds(): void
    {
        $evaluator = new FakeEvaluator(['Bug' => 0.9, 'Feature' => 0.2]);
        $labeler = new PullRequestLabeler(
            evaluator: $evaluator,
            policies: PolicyConfiguration::fromPolicies([
                new LabelPolicy('Bug', 'q', Probability::fromFloat(0.8), LabelApplication::Auto),
                new LabelPolicy('Feature', 'q', Probability::fromFloat(0.8), LabelApplication::Auto),
            ]),
            sources: ContextSources::default(),
            abstainMargin: Probability::fromFloat(0.05),
        );

        $outcome = $labeler->label(
            new PullRequestData('Fix it', null, [], [], null, []),
        );

        self::assertStringContainsString('Fix it', $evaluator->receivedContext->text());
        self::assertSame(['Bug'], $outcome->decisions->selectedLabels());
        self::assertSame('resolved-model-v9', $outcome->evaluation->metadata->model);
    }
}

final class FakeEvaluator implements Evaluator
{
    public PullRequestContext $receivedContext;

    /** @param array<string, float> $probabilities */
    public function __construct(
        private array $probabilities,
    ) {
    }

    public function evaluate(PullRequestContext $context): Evaluation
    {
        $this->receivedContext = $context;

        return new Evaluation(
            LabelProbabilities::fromMap($this->probabilities),
            new EvaluationMetadata('resolved-model-v9', []),
        );
    }
}
