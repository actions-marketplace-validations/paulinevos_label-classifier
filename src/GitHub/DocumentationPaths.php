<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\GitHub;

final class DocumentationPaths
{
    /** @param list<string> $paths */
    public function __construct(public array $paths)
    {
    }

    public static function defaults(): self
    {
        return new self(['README.md', 'UPGRADE.md', 'docs/en/index.rst']);
    }

    public static function fromCommaSeparated(string $value): self
    {
        $paths = [];

        foreach (explode(',', $value) as $path) {
            $trimmed = trim($path);

            if ($trimmed !== '') {
                $paths[] = $trimmed;
            }
        }

        return new self($paths);
    }
}
