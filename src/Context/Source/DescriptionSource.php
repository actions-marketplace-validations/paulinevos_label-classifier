<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context\Source;

use Vos\LabelClassifier\Context\ContextSection;
use Vos\LabelClassifier\Context\ContextSource;
use Vos\LabelClassifier\Context\PullRequestData;

final class DescriptionSource implements ContextSource
{
    private const CHARACTER_BUDGET = 4000;

    public function name(): string
    {
        return 'Description';
    }

    public function section(PullRequestData $data): ContextSection
    {
        if ($data->description === null) {
            return ContextSection::missing($this->name(), 'the description is empty or was not retrieved');
        }

        return ContextSection::present($this->name(), $data->description, self::CHARACTER_BUDGET);
    }
}
