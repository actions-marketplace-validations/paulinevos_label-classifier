<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context\Source;

use Vos\LabelClassifier\Context\ContextSection;
use Vos\LabelClassifier\Context\ContextSource;
use Vos\LabelClassifier\Context\PullRequestData;

final class TitleSource implements ContextSource
{
    private const CHARACTER_BUDGET = 500;

    public function name(): string
    {
        return 'Title';
    }

    public function section(PullRequestData $data): ContextSection
    {
        if ($data->title === null) {
            return ContextSection::missing($this->name(), 'the title was not retrieved');
        }

        return ContextSection::present($this->name(), $data->title, self::CHARACTER_BUDGET);
    }
}
