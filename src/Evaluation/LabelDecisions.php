<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Evaluation;

use Vos\LabelClassifier\Policy\LabelApplication;
use Vos\LabelClassifier\Policy\LabelPolicy;
use Vos\LabelClassifier\Policy\PolicyConfiguration;
use Vos\LabelClassifier\Policy\Probability;

final class LabelDecisions
{
    /** @param list<LabelDecision> $decisions */
    private function __construct(private array $decisions)
    {
    }

    public static function from(
        PolicyConfiguration $policies,
        LabelProbabilities $probabilities,
        Probability $abstainMargin,
    ): self {
        $decisions = [];

        foreach ($policies->all() as $policy) {
            $decisions[] = self::decision($policy, $probabilities, $abstainMargin);
        }

        return new self($decisions);
    }

    private static function decision(
        LabelPolicy $policy,
        LabelProbabilities $probabilities,
        Probability $abstainMargin,
    ): LabelDecision {
        $probability = $probabilities->probability($policy->label);

        if ($probability === null) {
            return new LabelDecision($policy, null, Decision::Rejected);
        }

        if (Probability::fromFloat($probability)->isAtLeast($policy->threshold)) {
            return new LabelDecision($policy, $probability, self::kindForApplication($policy));
        }

        $abstainBelow = Probability::fromFloat($policy->threshold->value - $abstainMargin->value);

        if ($abstainMargin->value > 0.0 && Probability::fromFloat($probability)->isAtLeast($abstainBelow)) {
            return new LabelDecision($policy, $probability, Decision::Abstained);
        }

        return new LabelDecision($policy, $probability, Decision::Rejected);
    }

    private static function kindForApplication(LabelPolicy $policy): Decision
    {
        if ($policy->application === LabelApplication::Auto) {
            return Decision::Selected;
        }

        return Decision::Advisory;
    }

    /** Labels to apply to the pull request, existing labels preserved.
     *  @return list<string>
     */
    public function selectedLabels(): array
    {
        return $this->labelsOfKind(Decision::Selected);
    }

    /** Labels that met their threshold without being applied automatically.
     *  @return list<string>
     */
    public function advisoryLabels(): array
    {
        return $this->labelsOfKind(Decision::Advisory);
    }

    /** @return list<string> */
    public function abstainedLabels(): array
    {
        return $this->labelsOfKind(Decision::Abstained);
    }

    /** @return list<LabelDecision> */
    public function all(): array
    {
        return $this->decisions;
    }

    /** @return list<string> */
    private function labelsOfKind(Decision $kind): array
    {
        $labels = [];

        foreach ($this->decisions as $decision) {
            if ($decision->kind === $kind) {
                $labels[] = $decision->policy->label;
            }
        }

        return $labels;
    }
}
