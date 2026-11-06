<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Policy;

use RuntimeException;

/** A float that is always within the inclusive range 0 to 1. */
final class Probability
{
    private function __construct(public float $value)
    {
    }

    public static function fromFloat(float $value): self
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new RuntimeException(sprintf('A probability must be between 0 and 1, got %s.', $value));
        }

        return new self($value);
    }

    /** @throws RuntimeException when the string is not a probability */
    public static function fromString(string $value): self
    {
        if (is_numeric($value)) {
            return self::fromFloat((float) $value);
        }

        throw new RuntimeException(sprintf('"%s" is not a probability between 0 and 1.', $value));
    }

    /** Clamps out-of-range input instead of failing, for API answers. */
    public static function fromLooseFloat(float $value): self
    {
        return new self(min(1.0, max(0.0, $value)));
    }

    public function isAtLeast(self $other): bool
    {
        return $this->value >= $other->value;
    }
}
