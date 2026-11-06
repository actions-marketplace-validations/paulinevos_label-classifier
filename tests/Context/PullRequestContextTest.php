<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Tests\Context;

use Vos\LabelClassifier\Context\ContextSources;
use Vos\LabelClassifier\Context\PullRequestContext;
use Vos\LabelClassifier\Context\PullRequestData;
use PHPUnit\Framework\TestCase;

final class PullRequestContextTest extends TestCase
{
    public function testEmptyDataReportsEverySourceAsMissing(): void
    {
        $context = PullRequestContext::fromData(PullRequestData::empty(), ContextSources::default());

        self::assertSame(
            'missing: Title, Description, Commit messages, Changed file paths, Diff, Repository documentation',
            $context->completeness(),
        );
    }

    public function testCompleteDataReportsComplete(): void
    {
        $data = new PullRequestData(
            title: 'Fix the thing',
            description: 'It was broken.',
            commitMessages: ['Fix the thing'],
            changedFilePaths: ['src/Foo.php'],
            diff: 'diff --git a/src/Foo.php b/src/Foo.php',
            documentationFiles: ['README.md' => '# The thing'],
        );

        $context = PullRequestContext::fromData($data, ContextSources::default());

        self::assertSame('complete', $context->completeness());
        self::assertStringContainsString('## Title', $context->text());
        self::assertStringContainsString('## Repository documentation', $context->text());
    }

    public function testContextTextCarriesExplicitMarkersIntoTheEvaluationState(): void
    {
        $context = PullRequestContext::fromData(PullRequestData::empty(), ContextSources::default());

        self::assertStringContainsString('(not available:', $context->text());
    }
}
