<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Tests\Evaluation;

use Vos\LabelClassifier\Evaluation\Decision;
use Vos\LabelClassifier\Evaluation\LabelDecisions;
use Vos\LabelClassifier\Evaluation\LabelProbabilities;
use Vos\LabelClassifier\Policy\LabelApplication;
use Vos\LabelClassifier\Policy\LabelPolicy;
use Vos\LabelClassifier\Policy\PolicyConfiguration;
use Vos\LabelClassifier\Policy\Probability;
use PHPUnit\Framework\TestCase;

final class LabelDecisionsTest extends TestCase
{
    public function testAutoLabelAboveThresholdIsSelected(): void
    {
        $decisions = $this->decisions(['Bug' => 0.9], margin: 0.0);

        self::assertSame(['Bug'], $decisions->selectedLabels());
        self::assertSame([], $decisions->advisoryLabels());
    }

    public function testAdvisoryLabelAboveThresholdIsReportedNotApplied(): void
    {
        $policies = PolicyConfiguration::fromPolicies([
            $this->policy('Needs Test', 0.5, LabelApplication::Advisory),
        ]);

        $decisions = LabelDecisions::from($policies, LabelProbabilities::fromMap(['Needs Test' => 0.9]), $this->margin(0.0));

        self::assertSame([], $decisions->selectedLabels());
        self::assertSame(['Needs Test'], $decisions->advisoryLabels());
    }

    public function testProbabilityInsideTheMarginAbstains(): void
    {
        $decisions = $this->decisions(['Bug' => 0.77], margin: 0.05);

        self::assertSame([], $decisions->selectedLabels());
        self::assertSame(['Bug'], $decisions->abstainedLabels());
    }

    public function testProbabilityFarBelowTheThresholdIsRejected(): void
    {
        $decisions = $this->decisions(['Bug' => 0.1], margin: 0.05);

        foreach ($decisions->all() as $decision) {
            self::assertSame(Decision::Rejected, $decision->kind);
        }
    }

    public function testMissingAnswerIsRejected(): void
    {
        $decisions = $this->decisions([], margin: 0.0);

        self::assertSame([], $decisions->selectedLabels());
        self::assertNull($decisions->all()[0]->probability);
    }

    public function testManualLabelNeverBecomesSelected(): void
    {
        $policies = PolicyConfiguration::fromPolicies([
            $this->policy('Release Blocker', 0.5, LabelApplication::Manual),
        ]);

        $decisions = LabelDecisions::from($policies, LabelProbabilities::fromMap(['Release Blocker' => 1.0]), $this->margin(0.0));

        self::assertSame([], $decisions->selectedLabels());
        self::assertSame(['Release Blocker'], $decisions->advisoryLabels());
    }

    /** @param array<string, float> $probabilities */
    private function decisions(array $probabilities, float $margin): LabelDecisions
    {
        return LabelDecisions::from(
            PolicyConfiguration::fromPolicies([$this->policy('Bug', 0.8, LabelApplication::Auto)]),
            LabelProbabilities::fromMap($probabilities),
            $this->margin($margin),
        );
    }

    private function policy(string $label, float $threshold, LabelApplication $application): LabelPolicy
    {
        return new LabelPolicy($label, 'A question?', Probability::fromFloat($threshold), $application);
    }

    private function margin(float $value): Probability
    {
        return Probability::fromFloat($value);
    }
}
