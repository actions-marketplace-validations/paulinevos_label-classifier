<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context\Source;

use Vos\LabelClassifier\Context\ContextSection;
use Vos\LabelClassifier\Context\ContextSource;
use Vos\LabelClassifier\Context\PullRequestData;

final class CommitMessagesSource implements ContextSource
{
    private const CHARACTER_BUDGET = 4000;

    public function name(): string
    {
        return 'Commit messages';
    }

    public function section(PullRequestData $data): ContextSection
    {
        if ($data->commitMessages === []) {
            return ContextSection::missing($this->name(), 'no commit messages were retrieved');
        }

        return ContextSection::present($this->name(), implode("\n\n", $data->commitMessages), self::CHARACTER_BUDGET);
    }
}
