<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Tests\Context;

use Vos\LabelClassifier\Context\ContextSection;
use PHPUnit\Framework\TestCase;

final class ContextSectionTest extends TestCase
{
    public function testShortContentIsRenderedVerbatim(): void
    {
        $section = ContextSection::present('Title', 'Fix the thing', 500);

        self::assertSame("## Title\nFix the thing", $section->text());
        self::assertTrue($section->isPresent());
        self::assertFalse($section->truncated);
    }

    public function testLongContentIsTruncatedAndSaysSo(): void
    {
        $section = ContextSection::present('Diff', str_repeat('a', 101), 100);

        self::assertTrue($section->truncated);
        self::assertSame(100, mb_strlen((string) $section->content));
        self::assertStringContainsString('(truncated to the first 100 characters)', $section->text());
        self::assertSame(str_repeat('a', 100), mb_substr($section->text(), -100));
    }

    public function testMissingSectionStatesItsReason(): void
    {
        $section = ContextSection::missing('Diff', 'the diff was not retrieved');

        self::assertFalse($section->isPresent());
        self::assertSame("## Diff\n(not available: the diff was not retrieved)", $section->text());
    }
}
