<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Evaluation;

use Vos\LabelClassifier\Policy\LabelPolicy;

final class LabelDecision
{
    public function __construct(
        public LabelPolicy $policy,
        public ?float $probability,
        public Decision $kind,
    ) {
    }
}
