<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Infrastructure\Jev;

final class JevResult
{
    /**
     * @param array<string, float> $probabilities by question id
     * @param array<string, mixed> $usage as reported by the API
     */
    private function __construct(
        public array $probabilities,
        public ?string $model,
        public array $usage,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            probabilities: self::probabilities($payload),
            model: self::model($payload),
            usage: self::usage($payload),
        );
    }

    public function probability(string $questionId): ?float
    {
        return $this->probabilities[$questionId] ?? null;
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, float>
     */
    private static function probabilities(array $payload): array
    {
        $answers = $payload['answers'] ?? null;

        if (! is_array($answers)) {
            throw JevError::invalidPayload('the "answers" map is missing.');
        }

        $probabilities = [];

        foreach ($answers as $questionId => $answer) {
            if (! is_array($answer)) {
                throw JevError::invalidPayload(sprintf('the answer for "%s" is not an object.', (string) $questionId));
            }

            $probabilities[(string) $questionId] = self::answerProbability((string) $questionId, $answer);
        }

        return $probabilities;
    }

    /** @param array<string, mixed> $answer */
    private static function answerProbability(string $questionId, array $answer): float
    {
        $noul = $answer['noul'] ?? null;

        if (is_int($noul) || is_float($noul)) {
            return (float) $noul;
        }

        if (is_array($noul) && is_numeric($noul['probability'] ?? null)) {
            return (float) $noul['probability'];
        }

        throw JevError::invalidPayload(sprintf(
            'the "noul" answer for question "%s" holds no probability.',
            $questionId,
        ));
    }

    /** @param array<string, mixed> $payload */
    private static function model(array $payload): ?string
    {
        $model = $payload['model'] ?? null;

        if (! is_string($model) || $model === '') {
            return null;
        }

        return $model;
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private static function usage(array $payload): array
    {
        $usage = $payload['usage'] ?? [];

        return is_array($usage) ? $usage : [];
    }
}
