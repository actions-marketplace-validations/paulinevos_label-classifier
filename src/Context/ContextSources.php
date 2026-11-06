<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context;

use Vos\LabelClassifier\Context\Source\ChangedFilePathsSource;
use Vos\LabelClassifier\Context\Source\CommitMessagesSource;
use Vos\LabelClassifier\Context\Source\DescriptionSource;
use Vos\LabelClassifier\Context\Source\DiffSource;
use Vos\LabelClassifier\Context\Source\DocumentationSource;
use Vos\LabelClassifier\Context\Source\TitleSource;

final class ContextSources
{
    /** @param list<ContextSource> $sources */
    public function __construct(private array $sources)
    {
    }

    public static function default(): self
    {
        return new self([
            new TitleSource(),
            new DescriptionSource(),
            new CommitMessagesSource(),
            new ChangedFilePathsSource(),
            new DiffSource(),
            new DocumentationSource(),
        ]);
    }

    /** @param list<ContextSource> $sources */
    public static function fromList(array $sources): self
    {
        return new self($sources);
    }

    /** @return list<ContextSection> */
    public function sections(PullRequestData $data): array
    {
        $sections = [];

        foreach ($this->sources as $source) {
            $sections[] = $source->section($data);
        }

        return $sections;
    }
}
