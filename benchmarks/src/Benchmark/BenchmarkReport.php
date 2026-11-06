<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Benchmark;

use Vos\LabelClassifier\Evaluation\LabelDecisions;

final class BenchmarkReport
{
    /** @param list<BenchmarkOutcome> $outcomes */
    private function __construct(
        private array $outcomes,
        public LabelAliases $aliases,
    ) {
    }

    /** @param list<BenchmarkOutcome> $outcomes */
    public static function fromOutcomes(array $outcomes, LabelAliases $aliases): self
    {
        return new self($outcomes, $aliases);
    }

    public function caseCount(): int
    {
        return count($this->outcomes);
    }

    public function totalLatencyMilliseconds(): float
    {
        $total = 0.0;

        foreach ($this->outcomes as $outcome) {
            $total += $outcome->latencyMilliseconds;
        }

        return $total;
    }

    /** @return array<string, float|int> numeric usage fields summed over all evaluations */
    public function usageTotals(): array
    {
        $totals = [];

        foreach ($this->outcomes as $outcome) {
            foreach ($outcome->labeling->evaluation->metadata->usage as $field => $value) {
                if (is_int($value) || is_float($value)) {
                    $totals[(string) $field] = ($totals[(string) $field] ?? 0) + $value;
                }
            }
        }

        return $totals;
    }

    public function resolvedModel(): ?string
    {
        foreach ($this->outcomes as $outcome) {
            $model = $outcome->labeling->evaluation->metadata->model;

            if ($model !== null) {
                return $model;
            }
        }

        return null;
    }

    /** @return array<string, LabelMetrics> */
    public function metrics(): array
    {
        $metrics = [];

        foreach ($this->outcomes as $outcome) {
            $expected = $this->aliases->normalizeAll($outcome->case->observedLabels);
            $predicted = $this->predicted($outcome->labeling->decisions);
            $abstained = $outcome->labeling->decisions->abstainedLabels();

            foreach (array_unique([...$expected, ...$predicted]) as $label) {
                $perLabel = LabelMetrics::forLabel($label, $expected, $predicted, $abstained);
                $metrics[$label] = isset($metrics[$label])
                    ? $metrics[$label]->plus($perLabel)
                    : $perLabel;
            }
        }

        ksort($metrics);

        return $metrics;
    }

    /** @return list<string> */
    private function predicted(LabelDecisions $decisions): array
    {
        return array_merge($decisions->selectedLabels(), $decisions->advisoryLabels());
    }

    /**
     * One record per case with everything an annotation pass needs: the
     * per-label probabilities and decision kinds, the labels expected from the
     * frozen ground truth, and the full frozen input.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->outcomes as $outcome) {
            $probabilities = [];
            $kinds = [];

            foreach ($outcome->labeling->decisions->all() as $decision) {
                $probabilities[$decision->policy->label] = $decision->probability;
                $kinds[$decision->policy->label] = $decision->kind->value;
            }

            $rows[] = [
                'id' => $outcome->case->identifier,
                'releases' => $outcome->case->releases,
                'expected' => $this->aliases->normalizeAll($outcome->case->observedLabels),
                'probabilities' => $probabilities,
                'kinds' => $kinds,
                'latencyMilliseconds' => $outcome->latencyMilliseconds,
                'contextCompleteness' => $outcome->labeling->context->completeness(),
                'data' => $outcome->case->data->toArray(),
            ];
        }

        return $rows;
    }
}
