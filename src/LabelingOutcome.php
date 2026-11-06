<?php

declare(strict_types=1);

namespace Vos\LabelClassifier;

use Vos\LabelClassifier\Context\PullRequestContext;
use Vos\LabelClassifier\Evaluation\Evaluation;
use Vos\LabelClassifier\Evaluation\LabelDecisions;

final class LabelingOutcome
{
    public function __construct(
        public PullRequestContext $context,
        public Evaluation $evaluation,
        public LabelDecisions $decisions,
    ) {
    }
}
