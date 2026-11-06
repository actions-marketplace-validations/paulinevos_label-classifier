<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Benchmark;

/**
 * Explicit normalization of historical label names onto the canonical set, so
 * frozen manifests can be scored against current policy configuration.
 */
final class LabelAliases
{
    /** @param array<string, string> $aliases historical name → canonical name */
    public function __construct(private array $aliases)
    {
    }

    /** @param array<string, mixed> $aliases */
    public static function fromArray(array $aliases): self
    {
        $normalized = [];

        foreach ($aliases as $historical => $canonical) {
            if (is_string($canonical)) {
                $normalized[$historical] = $canonical;
            }
        }

        return new self($normalized);
    }

    public function normalize(string $label): string
    {
        return $this->aliases[$label] ?? $label;
    }

    /** @param list<string> $labels
     *  @return list<string>
     */
    public function normalizeAll(array $labels): array
    {
        $normalized = [];

        foreach ($labels as $label) {
            $canonical = $this->normalize($label);

            if (! in_array($canonical, $normalized, true)) {
                $normalized[] = $canonical;
            }
        }

        return $normalized;
    }
}
