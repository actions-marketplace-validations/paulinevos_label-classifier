<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\GitHub;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GitHubHttp
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $apiUrl,
        private readonly string $token,
    ) {
    }

    /** @return array<string, mixed> */
    public function getJson(string $path): array
    {
        $response = $this->send('GET', $this->url($path));
        $this->assertSuccessful($response, 'GET', $path);

        return $response->toArray(throw: false);
    }

    /** @return list<array<string, mixed>> */
    public function paginate(string $path): array
    {
        $items = [];
        $url = $this->url($path) . (str_contains($path, '?') ? '&' : '?') . 'per_page=100';

        while ($url !== null) {
            $response = $this->send('GET', $url);
            $this->assertSuccessful($response, 'GET', $path);
            $page = $response->toArray(throw: false);

            if (! array_is_list($page)) {
                throw new RuntimeException(sprintf('Expected a list from GitHub API GET %s.', $path));
            }

            $items = [...$items, ...$page];
            $link = $response->getHeaders(throw: false)['link'][0] ?? null;
            $url = self::nextPageUrl($link);
        }

        return $items;
    }

    public function getRaw(string $path, string $accept): ?string
    {
        $response = $this->send('GET', $this->url($path), headers: ['Accept' => $accept]);

        if ($response->getStatusCode() === 404) {
            return null;
        }

        $this->assertSuccessful($response, 'GET', $path);

        return $response->getContent(throw: false);
    }

    /** @param array<string, mixed> $body */
    public function post(string $path, array $body): void
    {
        $response = $this->send('POST', $this->url($path), body: $body);
        $this->assertSuccessful($response, 'POST', $path);
    }

    /** Joins API paths correctly whether or not they carry a leading slash. */
    private function url(string $pathOrUrl): string
    {
        if (str_starts_with($pathOrUrl, 'http')) {
            return $pathOrUrl;
        }

        return rtrim($this->apiUrl, '/') . '/' . ltrim($pathOrUrl, '/');
    }

    /** @param array<string, string> $headers
     *  @param array<string, mixed>|null $body
     */
    private function send(string $method, string $url, array $headers = [], ?array $body = null): ResponseInterface
    {
        $options = ['headers' => [...$this->headers(), ...$headers]];

        if ($body !== null) {
            $options['json'] = $body;
        }

        return $this->http->request($method, $url, options: $options);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'label-classifier',
        ];
    }

    private function assertSuccessful(ResponseInterface $response, string $method, string $path): void
    {
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return;
        }

        throw new RuntimeException(sprintf(
            'GitHub API %s %s failed with %d: %s',
            $method,
            $path,
            $status,
            $response->getContent(throw: false),
        ));
    }

    private static function nextPageUrl(?string $linkHeader): ?string
    {
        if ($linkHeader === null || preg_match('/<([^>]+)>;\s*rel="next"/', $linkHeader, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
