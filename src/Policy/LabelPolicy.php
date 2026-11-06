<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Policy;

final class LabelPolicy
{
    public function __construct(
        public string $label,
        public string $question,
        public Probability $threshold,
        public LabelApplication $application,
    ) {
    }
}
