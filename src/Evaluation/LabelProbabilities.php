<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Evaluation;

final class LabelProbabilities
{
    /** @param array<string, float> $probabilities */
    private function __construct(private array $probabilities)
    {
    }

    /** @param array<string, float> $probabilities */
    public static function fromMap(array $probabilities): self
    {
        $clamped = [];

        foreach ($probabilities as $label => $probability) {
            $clamped[(string) $label] = min(1.0, max(0.0, (float) $probability));
        }

        return new self($clamped);
    }

    public function probability(string $label): ?float
    {
        return $this->probabilities[$label] ?? null;
    }

    /** @return list<string> */
    public function labels(): array
    {
        return array_keys($this->probabilities);
    }
}
