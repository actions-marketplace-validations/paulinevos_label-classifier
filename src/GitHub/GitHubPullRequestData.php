<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\GitHub;

use Vos\LabelClassifier\Context\PullRequestData;
use RuntimeException;

final class GitHubPullRequestData
{
    public function __construct(
        private GitHubHttp $http,
        private RepositoryName $repository,
        private int $number,
        private DocumentationPaths $documentationPaths,
    ) {
    }

    public function fetch(): PullRequestData
    {
        $pullRequest = $this->http->getJson($this->path());
        $baseRef = (string) ($pullRequest['base']['ref'] ?? '');

        return new PullRequestData(
            title: self::optionalString($pullRequest['title'] ?? null),
            description: self::optionalString($pullRequest['body'] ?? null),
            commitMessages: $this->commitMessages(),
            changedFilePaths: $this->changedFilePaths(),
            diff: $this->diff(),
            documentationFiles: $this->documentationFiles($baseRef),
        );
    }

    /** @return list<string> */
    private function commitMessages(): array
    {
        $messages = [];

        foreach ($this->http->paginate(sprintf('%s/pulls/%d/commits', $this->repository->path(), $this->number)) as $commit) {
            $message = $commit['commit']['message'] ?? null;

            if (is_string($message) && $message !== '') {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /** @return list<string> */
    private function changedFilePaths(): array
    {
        $paths = [];

        foreach ($this->http->paginate(sprintf('%s/pulls/%d/files', $this->repository->path(), $this->number)) as $file) {
            if (is_string($file['filename'] ?? null)) {
                $paths[] = $file['filename'];
            }
        }

        return $paths;
    }

    private function diff(): ?string
    {
        return $this->http->getRaw($this->path(), 'application/vnd.github.diff');
    }

    /** @return array<string, string> */
    private function documentationFiles(string $baseRef): array
    {
        if ($baseRef === '') {
            throw new RuntimeException(sprintf(
                'The pull request payload for %s is missing "base.ref".',
                $this->reference(),
            ));
        }

        $files = [];

        foreach ($this->documentationPaths->paths as $path) {
            $content = $this->documentationFile($baseRef, $path);

            if ($content !== null) {
                $files[$path] = $content;
            }
        }

        return $files;
    }

    private function documentationFile(string $baseRef, string $path): ?string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return $this->http->getRaw(
            sprintf('%s/contents/%s?ref=%s', $this->repository->path(), $encoded, rawurlencode($baseRef)),
            'application/vnd.github.raw',
        );
    }

    private function path(): string
    {
        return sprintf('%s/pulls/%d', $this->repository->path(), $this->number);
    }

    private function reference(): string
    {
        return $this->repository->pullRequestReference($this->number);
    }

    private static function optionalString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
