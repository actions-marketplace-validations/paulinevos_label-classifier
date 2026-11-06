<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context\Source;

use Vos\LabelClassifier\Context\ContextSection;
use Vos\LabelClassifier\Context\ContextSource;
use Vos\LabelClassifier\Context\PullRequestData;

final class DocumentationSource implements ContextSource
{
    private const CHARACTER_BUDGET = 6000;

    public function name(): string
    {
        return 'Repository documentation';
    }

    public function section(PullRequestData $data): ContextSection
    {
        if ($data->documentationFiles === []) {
            return ContextSection::missing($this->name(), 'no documentation files were retrieved');
        }

        return ContextSection::present($this->name(), $this->combined($data), self::CHARACTER_BUDGET);
    }

    private function combined(PullRequestData $data): string
    {
        $parts = [];

        foreach ($data->documentationFiles as $path => $content) {
            $parts[] = sprintf("### %s\n%s", $path, $content);
        }

        return implode("\n\n", $parts);
    }
}
