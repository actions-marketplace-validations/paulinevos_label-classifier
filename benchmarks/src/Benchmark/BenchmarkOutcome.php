<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Benchmark;

use Vos\LabelClassifier\LabelingOutcome;

final class BenchmarkOutcome
{
    public function __construct(
        public BenchmarkCase $case,
        public LabelingOutcome $labeling,
        public float $latencyMilliseconds,
    ) {
    }
}
