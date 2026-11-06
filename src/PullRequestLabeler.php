<?php

declare(strict_types=1);

namespace Vos\LabelClassifier;

use Vos\LabelClassifier\Context\ContextSources;
use Vos\LabelClassifier\Context\PullRequestContext;
use Vos\LabelClassifier\Context\PullRequestData;
use Vos\LabelClassifier\Evaluation\Evaluator;
use Vos\LabelClassifier\Evaluation\LabelDecisions;
use Vos\LabelClassifier\Policy\PolicyConfiguration;
use Vos\LabelClassifier\Policy\Probability;

final class PullRequestLabeler
{
    public function __construct(
        private Evaluator $evaluator,
        private PolicyConfiguration $policies,
        private ContextSources $sources,
        private Probability $abstainMargin,
    ) {
    }

    public function label(PullRequestData $data): LabelingOutcome
    {
        $context = PullRequestContext::fromData($data, $this->sources);
        $evaluation = $this->evaluator->evaluate($context);

        return new LabelingOutcome(
            context: $context,
            evaluation: $evaluation,
            decisions: LabelDecisions::from($this->policies, $evaluation->probabilities, $this->abstainMargin),
        );
    }
}
