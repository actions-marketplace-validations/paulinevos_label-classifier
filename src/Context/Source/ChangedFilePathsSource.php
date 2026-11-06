<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context\Source;

use Vos\LabelClassifier\Context\ContextSection;
use Vos\LabelClassifier\Context\ContextSource;
use Vos\LabelClassifier\Context\PullRequestData;

final class ChangedFilePathsSource implements ContextSource
{
    private const CHARACTER_BUDGET = 2000;

    public function name(): string
    {
        return 'Changed file paths';
    }

    public function section(PullRequestData $data): ContextSection
    {
        if ($data->changedFilePaths === []) {
            return ContextSection::missing($this->name(), 'no changed files were retrieved');
        }

        return ContextSection::present($this->name(), implode("\n", $data->changedFilePaths), self::CHARACTER_BUDGET);
    }
}
