<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Tests\GitHub;

use Vos\LabelClassifier\GitHub\DocumentationPaths;
use Vos\LabelClassifier\GitHub\GitHubHttp;
use Vos\LabelClassifier\GitHub\GitHubPullRequestData;
use Vos\LabelClassifier\GitHub\RepositoryName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GitHubPullRequestDataTest extends TestCase
{
    public function testFetchMapsEveryFieldFromTheApiResponses(): void
    {
        $http = new GitHubHttp(new MockHttpClient($this->responses(...)), 'https://api.github.com', 'token');

        $data = (new GitHubPullRequestData(
            $http,
            RepositoryName::fromString('acme/widgets'),
            123,
            DocumentationPaths::fromCommaSeparated('README.md,MISSING.md'),
        ))->fetch();

        self::assertSame('Fix lazy loading', $data->title);
        self::assertSame('Lazy loading broke.', $data->description);
        self::assertSame(['Fix lazy loading'], $data->commitMessages);
        self::assertSame(['src/LazyLoader.php'], $data->changedFilePaths);
        self::assertStringContainsString('diff --git', (string) $data->diff);
        self::assertSame(['README.md' => '# ACME Widgets'], $data->documentationFiles);
    }

    /** @param array<string, mixed> $options */
    private function responses(string $method, string $url, array $options = []): MockResponse
    {
        $accept = '';

        foreach ($options['headers'] ?? [] as $header) {
            if (str_starts_with((string) $header, 'Accept:')) {
                $accept = trim(substr((string) $header, 7));
            }
        }

        if (str_contains($url, '/pulls/123/commits')) {
            return new MockResponse('[{"commit": {"message": "Fix lazy loading"}}]');
        }

        if (str_contains($url, '/pulls/123/files')) {
            return new MockResponse('[{"filename": "src/LazyLoader.php"}]');
        }

        if (str_ends_with($url, '/pulls/123') && $accept === 'application/vnd.github.diff') {
            return new MockResponse("diff --git a/src/LazyLoader.php b/src/LazyLoader.php");
        }

        if (str_contains($url, '/contents/README.md')) {
            return new MockResponse('# ACME Widgets');
        }

        if (str_contains($url, '/contents/MISSING.md')) {
            return new MockResponse('', ['http_code' => 404]);
        }

        return new MockResponse(json_encode([
            'number' => 123,
            'title' => 'Fix lazy loading',
            'body' => 'Lazy loading broke.',
            'base' => ['ref' => '3.4.x'],
        ], JSON_THROW_ON_ERROR));
    }
}
