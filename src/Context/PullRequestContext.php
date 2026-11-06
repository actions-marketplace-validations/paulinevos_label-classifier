<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context;

final class PullRequestContext
{
    /** @param list<ContextSection> $sections */
    private function __construct(private array $sections)
    {
    }

    public static function fromData(PullRequestData $data, ContextSources $sources): self
    {
        return new self($sources->sections($data));
    }

    /** The full text sent to the evaluator as the shared state. */
    public function text(): string
    {
        $texts = [];

        foreach ($this->sections as $section) {
            $texts[] = $section->text();
        }

        return implode("\n\n", $texts);
    }

    /** One-line account of what the evaluator will not see, or "complete". */
    public function completeness(): string
    {
        $missing = [];
        $truncated = [];

        foreach ($this->sections as $section) {
            $this->collect($section, $missing, $truncated);
        }

        if ($missing === [] && $truncated === []) {
            return 'complete';
        }

        $parts = [];

        if ($missing !== []) {
            $parts[] = 'missing: ' . implode(', ', $missing);
        }

        if ($truncated !== []) {
            $parts[] = 'truncated: ' . implode(', ', $truncated);
        }

        return implode('; ', $parts);
    }

    /**
     * @param list<string> $missing
     * @param list<string> $truncated
     */
    private function collect(ContextSection $section, array &$missing, array &$truncated): void
    {
        if (! $section->isPresent()) {
            $missing[] = $section->name;

            return;
        }

        if ($section->truncated) {
            $truncated[] = $section->name;
        }
    }
}
