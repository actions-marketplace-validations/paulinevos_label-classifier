<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Tests\Jev;

use Vos\LabelClassifier\Context\ContextSources;
use Vos\LabelClassifier\Context\PullRequestContext;
use Vos\LabelClassifier\Context\PullRequestData;
use Vos\LabelClassifier\Infrastructure\Jev\JevClient;
use Vos\LabelClassifier\Infrastructure\Jev\JevEvaluator;
use Vos\LabelClassifier\Policy\LabelApplication;
use Vos\LabelClassifier\Policy\LabelPolicy;
use Vos\LabelClassifier\Policy\PolicyConfiguration;
use Vos\LabelClassifier\Policy\Probability;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class JevEvaluatorTest extends TestCase
{
    public function testOneRequestCarriesTheStateAndEveryQuestion(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use (&$requests): MockResponse {
            $requests[] = $options;

            return new MockResponse(json_encode(self::apiPayload(), JSON_THROW_ON_ERROR));
        });

        $evaluation = $this->evaluator($client)->evaluate($this->context());

        self::assertSame(1, $client->getRequestsCount());
        self::assertStringContainsString('"questions":{', $requests[0]['body']);
        $payload = json_decode($requests[0]['body'], true);
        self::assertSame('system-one-latest', $payload['model']);
        self::assertStringContainsString('Fix the thing', $payload['state']);
        self::assertCount(2, $payload['questions']);
        self::assertSame(['type' => 'noul'], array_diff_key($payload['questions']['q0'], ['instructions' => '']));
        self::assertSame('Is this a bug fix?', $payload['questions']['q0']['instructions']);
        self::assertSame('Is this a feature?', $payload['questions']['q1']['instructions']);

        self::assertSame(0.92, $evaluation->probabilities->probability('Bug'));
        self::assertSame(0.31, $evaluation->probabilities->probability('Feature'));
        self::assertSame('resolved-model-v9', $evaluation->metadata->model);
        self::assertSame(42, $evaluation->metadata->usage['prompt_tokens']);
    }

    public function testMissingAnswersAreOmittedSoTheyDecideAsRejected(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'model' => 'resolved-model-v9',
            'usage' => [],
            'answers' => ['q0' => ['noul' => 0.92]],
        ], JSON_THROW_ON_ERROR)));

        $evaluation = $this->evaluator($client)->evaluate($this->context());

        self::assertNull($evaluation->probabilities->probability('Feature'));
    }

    /** @return array<string, mixed> */
    private static function apiPayload(): array
    {
        return [
            'model' => 'resolved-model-v9',
            'usage' => ['prompt_tokens' => 42],
            'answers' => [
                'q0' => ['noul' => 0.92],
                'q1' => ['noul' => ['probability' => 0.31]],
            ],
        ];
    }

    private function evaluator(MockHttpClient $client): JevEvaluator
    {
        return new JevEvaluator(
            new JevClient($client, 'secret-key', 'system-one-latest'),
            PolicyConfiguration::fromPolicies([
                new LabelPolicy('Bug', 'Is this a bug fix?', Probability::fromFloat(0.8), LabelApplication::Auto),
                new LabelPolicy('Feature', 'Is this a feature?', Probability::fromFloat(0.8), LabelApplication::Auto),
            ]),
        );
    }

    private function context(): PullRequestContext
    {
        return PullRequestContext::fromData(
            new PullRequestData('Fix the thing', null, [], [], null, []),
            ContextSources::default(),
        );
    }
}
