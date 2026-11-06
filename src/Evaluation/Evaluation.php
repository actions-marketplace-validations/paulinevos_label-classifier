<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Evaluation;

final class Evaluation
{
    public function __construct(
        public LabelProbabilities $probabilities,
        public EvaluationMetadata $metadata,
    ) {
    }
}
