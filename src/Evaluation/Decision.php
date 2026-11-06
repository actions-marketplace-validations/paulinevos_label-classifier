<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Evaluation;

enum Decision: string
{
    case Selected = 'selected';
    case Advisory = 'advisory';
    case Abstained = 'abstained';
    case Rejected = 'rejected';
}
