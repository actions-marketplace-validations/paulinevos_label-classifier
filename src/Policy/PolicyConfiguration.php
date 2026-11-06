<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Policy;

use RuntimeException;

final class PolicyConfiguration
{
    /** @param list<LabelPolicy> $policies */
    private function __construct(private array $policies)
    {
    }

    /** @param list<LabelPolicy> $policies */
    public static function fromPolicies(array $policies): self
    {
        return new self($policies);
    }

    public static function fromFile(string $path, string $thresholdOverrides = ''): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read policy configuration at %s.', $path));
        }

        $config = json_decode($contents, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);

        if (! is_array($config)) {
            throw new RuntimeException('The policy configuration is not an object.');
        }

        return new self(self::fromArray($config, $thresholdOverrides));
    }

    /**
     * @param array<string, mixed> $config
     * @return list<LabelPolicy>
     */
    private static function fromArray(array $config, string $thresholdOverrides): array
    {
        $labels = $config['labels'] ?? null;

        if (! is_array($labels) || $labels === []) {
            throw new RuntimeException('The policy configuration must define a non-empty "labels" map.');
        }

        $overrides = self::parseThresholdOverrides($thresholdOverrides);
        $policies = [];

        foreach ($labels as $label => $specification) {
            $policies[] = self::policy((string) $label, $specification, $overrides);
        }

        $unknown = array_diff_key($overrides, $labels);

        if ($unknown !== []) {
            throw new RuntimeException(sprintf(
                'The threshold override names an unconfigured label: %s.',
                implode(', ', array_keys($unknown)),
            ));
        }

        return $policies;
    }

    /** @return array<string, Probability> */
    private static function parseThresholdOverrides(string $thresholdOverrides): array
    {
        $overrides = [];

        foreach (explode(',', $thresholdOverrides) as $override) {
            $trimmed = trim($override);

            if ($trimmed === '') {
                continue;
            }

            $parts = explode('=', $trimmed, 2);

            if (! isset($parts[1])) {
                throw new RuntimeException(sprintf(
                    'A threshold override must look like "Label=0.9", got "%s".',
                    $trimmed,
                ));
            }

            $overrides[trim($parts[0])] = Probability::fromString(trim($parts[1]));
        }

        return $overrides;
    }

    /** @param array<string, Probability> $overrides */
    private static function policy(string $label, mixed $specification, array $overrides): LabelPolicy
    {
        if (! is_array($specification)) {
            throw new RuntimeException(sprintf('The policy for label "%s" is not an object.', $label));
        }

        $question = $specification['question'] ?? null;

        if (! is_string($question) || $question === '') {
            throw new RuntimeException(sprintf('The policy for label "%s" must define a "question".', $label));
        }

        $application = LabelApplication::fromString((string) ($specification['application'] ?? 'auto'));

        return new LabelPolicy(
            label: $label,
            question: $question,
            threshold: $overrides[$label] ?? self::configuredThreshold($label, $specification),
            application: $application,
        );
    }

    /** @param array<string, mixed> $specification */
    private static function configuredThreshold(string $label, array $specification): Probability
    {
        $threshold = $specification['threshold'] ?? 0.8;

        if (! is_numeric($threshold)) {
            throw new RuntimeException(sprintf('The threshold for label "%s" is not numeric.', $label));
        }

        return Probability::fromFloat((float) $threshold);
    }

    /** @return list<LabelPolicy> */
    public function all(): array
    {
        return $this->policies;
    }

    public function count(): int
    {
        return count($this->policies);
    }

    /** @return list<string> */
    public function labelNames(): array
    {
        $names = [];

        foreach ($this->policies as $policy) {
            $names[] = $policy->label;
        }

        return $names;
    }
}
