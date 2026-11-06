<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Tests\Benchmark;

use Vos\LabelClassifier\Benchmark\LabelAliases;
use PHPUnit\Framework\TestCase;

final class LabelAliasesTest extends TestCase
{
    public function testHistoricalNamesMapOntoCanonicalOnes(): void
    {
        $aliases = LabelAliases::fromArray(['Bugfix' => 'Bug', 'feature' => 'Feature']);

        self::assertSame(['Bug', 'Feature'], $aliases->normalizeAll(['Bugfix', 'feature', 'Feature']));
    }

    public function testUnaliasedNamesPassThroughUnchanged(): void
    {
        $aliases = LabelAliases::fromArray([]);

        self::assertSame(['Bug'], $aliases->normalizeAll(['Bug']));
    }
}
