<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\GitHub;

use RuntimeException;

final class RepositoryName
{
    private function __construct(private string $owner, private string $name)
    {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('#^[^/\s]+/[^/\s]+$#', $value) !== 1) {
            throw new RuntimeException(sprintf('"%s" is not a repository in owner/name form.', $value));
        }

        [$owner, $name] = explode('/', $value, 2);

        return new self($owner, $name);
    }

    public function path(): string
    {
        return sprintf('/repos/%s/%s', $this->owner, $this->name);
    }

    public function pullRequestReference(int $number): string
    {
        return sprintf('%s#%d', $this->name, $number);
    }
}
