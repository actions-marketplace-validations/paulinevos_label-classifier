<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Context;

final class ContextSection
{
    private function __construct(
        public string $name,
        public ?string $content,
        public string $missingReason,
        public bool $truncated,
    ) {
    }

    public static function present(string $name, string $content, int $characterBudget): self
    {
        $truncated = mb_strlen($content) > $characterBudget;

        return new self(
            name: $name,
            content: $truncated ? mb_substr($content, 0, $characterBudget) : $content,
            missingReason: '',
            truncated: $truncated,
        );
    }

    public static function missing(string $name, string $reason): self
    {
        return new self(name: $name, content: null, missingReason: $reason, truncated: false);
    }

    public function isPresent(): bool
    {
        return $this->content !== null && $this->content !== '';
    }

    public function text(): string
    {
        if (! $this->isPresent()) {
            return sprintf("## %s\n(not available: %s)", $this->name, $this->missingReason);
        }

        if ($this->truncated) {
            return sprintf(
                "## %s\n(truncated to the first %d characters)\n%s",
                $this->name,
                mb_strlen((string) $this->content),
                $this->content,
            );
        }

        return sprintf("## %s\n%s", $this->name, $this->content);
    }
}
