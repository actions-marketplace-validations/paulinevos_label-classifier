<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Infrastructure\Jev;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The verified Jev System One API: one request carries the shared state and
 * every independent question; answers come back per question id.
 */
final class JevClient
{
    public const DEFAULT_ENDPOINT = 'https://api.typesafe.ai/v1/systemone';

    public function __construct(
        private HttpClientInterface $http,
        private string $apiKey,
        private string $model,
        private string $endpoint = self::DEFAULT_ENDPOINT,
    ) {
    }

    /** @param array<int|string, string> $questions question id → instructions */
    public function evaluate(string $state, array $questions): JevResult
    {
        $response = $this->http->request('POST', $this->endpoint, options: [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'state' => $state,
                'questions' => $this->requestQuestions($questions),
            ],
        ]);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw JevError::requestFailed($response->getStatusCode(), $response->getContent(throw: false));
        }

        return JevResult::fromPayload($response->toArray(throw: false));
    }

    /** @param array<int|string, string> $questions question id → instructions
     *  @return array<string, array<string, string>>
     */
    private function requestQuestions(array $questions): array
    {
        $payload = [];

        // String ids: PHP would otherwise collapse sequential numeric keys into
        // a JSON array, but the API requires an object keyed by question id.
        foreach ($questions as $id => $instructions) {
            $payload['q' . $id] = ['type' => 'noul', 'instructions' => $instructions];
        }

        return $payload;
    }
}
