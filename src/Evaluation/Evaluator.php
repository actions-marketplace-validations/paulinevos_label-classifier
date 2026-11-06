<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Evaluation;

use Vos\LabelClassifier\Context\PullRequestContext;

/**
 * Answers one independent yes/no question per configured label against the
 * shared pull request context. Implementations decide how questions are asked
 * — the Jev adapter sends them all in a single request.
 */
interface Evaluator
{
    public function evaluate(PullRequestContext $context): Evaluation;
}
