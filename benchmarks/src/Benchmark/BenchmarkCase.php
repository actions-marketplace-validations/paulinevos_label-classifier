<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Benchmark;

use Vos\LabelClassifier\Context\PullRequestData;

final class BenchmarkCase
{
    /**
     * @param list<string> $observedLabels the labels as seen at collection time,
     * before alias normalization
     * @param list<string> $releases
     */
    public function __construct(
        public string $identifier,
        public PullRequestData $data,
        public array $observedLabels,
        public array $releases,
        public ?string $revision,
        public ?string $mergedAt,
        public ?string $collectedAt,
        public ?string $group,
    ) {
    }

    /** @param array<string, mixed> $entry */
    public static function fromArray(array $entry): self
    {
        return new self(
            identifier: (string) ($entry['id'] ?? 'unknown'),
            data: self::data($entry['data'] ?? null),
            observedLabels: self::stringList($entry['observedLabels'] ?? []),
            releases: self::stringList($entry['releases'] ?? []),
            revision: self::optionalString($entry['revision'] ?? null),
            mergedAt: self::optionalString($entry['mergedAt'] ?? null),
            collectedAt: self::optionalString($entry['collectedAt'] ?? null),
            group: self::optionalString($entry['group'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->identifier,
            'data' => $this->data->toArray(),
            'observedLabels' => $this->observedLabels,
            'releases' => $this->releases,
            'revision' => $this->revision,
            'mergedAt' => $this->mergedAt,
            'collectedAt' => $this->collectedAt,
            'group' => $this->group,
        ];
    }

    private static function data(mixed $data): PullRequestData
    {
        if (! is_array($data)) {
            return PullRequestData::empty();
        }

        return PullRequestData::fromArray($data);
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    private static function optionalString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
