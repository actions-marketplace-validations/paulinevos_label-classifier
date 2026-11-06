<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Benchmark;

use Vos\LabelClassifier\Benchmark\LabelAliases;
use RuntimeException;

final class BenchmarkManifest
{
    /** @param list<BenchmarkCase> $cases */
    private function __construct(
        private array $cases,
        public LabelAliases $aliases,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read the benchmark manifest at %s.', $path));
        }

        $manifest = json_decode($contents, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);

        if (! is_array($manifest)) {
            throw new RuntimeException('The benchmark manifest is not an object.');
        }

        return new self(
            cases: self::casesFrom($manifest, $path),
            aliases: LabelAliases::fromArray($manifest['aliases'] ?? []),
        );
    }

    /** @return list<BenchmarkCase> */
    public function cases(): array
    {
        return $this->cases;
    }

    /** @param array<string, mixed> $manifest
     *  @return list<BenchmarkCase>
     */
    private static function casesFrom(array $manifest, string $path): array
    {
        $entries = $manifest['cases'] ?? null;

        if (! is_array($entries) || $entries === []) {
            throw new RuntimeException(sprintf('The benchmark manifest at %s defines no cases.', $path));
        }

        $cases = [];

        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $cases[] = BenchmarkCase::fromArray($entry);
            }
        }

        return $cases;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $entries = [];

        foreach ($this->cases as $case) {
            $entries[] = $case->toArray();
        }

        return ['aliases' => [], 'cases' => $entries];
    }
}
