<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context;

interface ContextSource
{
    public function name(): string;

    public function section(PullRequestData $data): ContextSection;
}
