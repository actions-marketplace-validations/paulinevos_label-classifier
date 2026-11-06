<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context\Source;

use Vos\LabelClassifier\Context\ContextSection;
use Vos\LabelClassifier\Context\ContextSource;
use Vos\LabelClassifier\Context\PullRequestData;

final class DiffSource implements ContextSource
{
    private const CHARACTER_BUDGET = 12000;

    public function name(): string
    {
        return 'Diff';
    }

    public function section(PullRequestData $data): ContextSection
    {
        if ($data->diff === null) {
            return ContextSection::missing($this->name(), 'the diff was not retrieved');
        }

        return ContextSection::present($this->name(), $data->diff, self::CHARACTER_BUDGET);
    }
}
