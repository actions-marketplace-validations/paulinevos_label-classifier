<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Evaluation;

/**
 * Kept alongside every evaluation so a run can be reproduced: the model as
 * resolved by the API and whatever usage metadata the API reported.
 *
 * @internal the usage array is passed through as reported, so callers can
 * aggregate it without this package knowing every provider's shape
 */
final class EvaluationMetadata
{
    /** @param array<string, mixed> $usage */
    public function __construct(
        public ?string $model,
        public array $usage,
    ) {
    }

    public static function none(): self
    {
        return new self(model: null, usage: []);
    }
}
