<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Benchmark;

use Vos\LabelClassifier\PullRequestLabeler;

final class BenchmarkRunner
{
    public function __construct(private PullRequestLabeler $labeler)
    {
    }

    public function run(BenchmarkManifest $manifest): BenchmarkReport
    {
        $outcomes = [];

        foreach ($manifest->cases() as $case) {
            $startedAt = hrtime(true);
            $labeling = $this->labeler->label($case->data);
            $latency = (hrtime(true) - $startedAt) / 1_000_000;

            $outcomes[] = new BenchmarkOutcome($case, $labeling, $latency);
        }

        return BenchmarkReport::fromOutcomes($outcomes, $manifest->aliases);
    }
}
