<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context;

use RuntimeException;

/**
 * The raw pull request input the context sources draw from. Freezing this
 * object's array form is what makes benchmark runs reproducible.
 */
final class PullRequestData
{
    /** @param list<string> $commitMessages
     *  @param list<string> $changedFilePaths
     *  @param array<string, string> $documentationFiles
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public array $commitMessages,
        public array $changedFilePaths,
        public ?string $diff,
        public array $documentationFiles,
    ) {
    }

    public static function empty(): self
    {
        return new self(null, null, [], [], null, []);
    }

    public static function fromFile(string $path): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read pull request data at %s.', $path));
        }

        $data = json_decode($contents, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException('The pull request data is not an object.');
        }

        return self::fromArray($data);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            title: self::optionalString($data['title'] ?? null),
            description: self::optionalString($data['description'] ?? null),
            commitMessages: self::stringList($data['commitMessages'] ?? []),
            changedFilePaths: self::stringList($data['changedFilePaths'] ?? []),
            diff: self::optionalString($data['diff'] ?? null),
            documentationFiles: self::stringMap($data['documentationFiles'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'commitMessages' => $this->commitMessages,
            'changedFilePaths' => $this->changedFilePaths,
            'diff' => $this->diff,
            'documentationFiles' => $this->documentationFiles,
        ];
    }

    private static function optionalString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
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

    /** @return array<string, string> */
    private static function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $strings[$key] = $item;
            }
        }

        return $strings;
    }
}
