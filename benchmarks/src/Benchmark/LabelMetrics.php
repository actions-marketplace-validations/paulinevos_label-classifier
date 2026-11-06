<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Benchmark;

final class LabelMetrics
{
    /** @param array<string, int> $counts */
    private function __construct(private array $counts)
    {
    }

    /** @param list<string> $expected
     *  @param list<string> $predicted
     *  @param list<string> $abstained
     */
    public static function forLabel(string $label, array $expected, array $predicted, array $abstained): self
    {
        return new self([
            'expected' => in_array($label, $expected, true) ? 1 : 0,
            'predicted' => in_array($label, $predicted, true) ? 1 : 0,
            'truePositive' => in_array($label, $expected, true) && in_array($label, $predicted, true) ? 1 : 0,
            'abstained' => in_array($label, $abstained, true) ? 1 : 0,
        ]);
    }

    public function plus(self $other): self
    {
        $sums = [];

        foreach (['expected', 'predicted', 'truePositive', 'abstained'] as $field) {
            $sums[$field] = $this->counts[$field] + $other->counts[$field];
        }

        return new self($sums);
    }

    public function truePositives(): int
    {
        return $this->counts['truePositive'];
    }

    public function falsePositives(): int
    {
        return $this->counts['predicted'] - $this->counts['truePositive'];
    }

    public function falseNegatives(): int
    {
        return $this->counts['expected'] - $this->counts['truePositive'];
    }

    public function expectedCount(): int
    {
        return $this->counts['expected'];
    }

    public function predictedCount(): int
    {
        return $this->counts['predicted'];
    }

    public function abstainedCount(): int
    {
        return $this->counts['abstained'];
    }

    /** Null when there is nothing to divide by; the report shows a dash. */
    public function precision(): ?float
    {
        return $this->counts['predicted'] === 0
            ? null
            : $this->counts['truePositive'] / $this->counts['predicted'];
    }

    public function recall(): ?float
    {
        return $this->counts['expected'] === 0
            ? null
            : $this->counts['truePositive'] / $this->counts['expected'];
    }
}
