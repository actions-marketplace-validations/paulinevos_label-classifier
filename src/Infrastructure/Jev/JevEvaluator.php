<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Infrastructure\Jev;

use Vos\LabelClassifier\Context\PullRequestContext;
use Vos\LabelClassifier\Evaluation\Evaluation;
use Vos\LabelClassifier\Evaluation\EvaluationMetadata;
use Vos\LabelClassifier\Evaluation\Evaluator;
use Vos\LabelClassifier\Evaluation\LabelProbabilities;
use Vos\LabelClassifier\Policy\PolicyConfiguration;

/**
 * The Jev adapter for the evaluator interface. All policy questions travel in
 * one request over the shared context; combining them with thresholds happens
 * in PHP, outside this class.
 */
final class JevEvaluator implements Evaluator
{
    public function __construct(
        private JevClient $client,
        private PolicyConfiguration $policies,
    ) {
    }

    public function evaluate(PullRequestContext $context): Evaluation
    {
        $result = $this->client->evaluate($context->text(), $this->questions());

        return new Evaluation(
            probabilities: $this->probabilities($result),
            metadata: new EvaluationMetadata($result->model, $result->usage),
        );
    }

    /** @return array<int, string> */
    private function questions(): array
    {
        $questions = [];

        foreach ($this->policies->all() as $index => $policy) {
            $questions[$index] = $policy->question;
        }

        return $questions;
    }

    private function probabilities(JevResult $result): LabelProbabilities
    {
        $probabilities = [];

        foreach ($this->questions() as $index => $question) {
            $probability = $result->probability('q' . $index);

            if ($probability !== null) {
                $probabilities[$this->labelOf($index)] = $probability;
            }
        }

        return LabelProbabilities::fromMap($probabilities);
    }

    private function labelOf(int $index): string
    {
        return $this->policies->all()[$index]->label;
    }
}
