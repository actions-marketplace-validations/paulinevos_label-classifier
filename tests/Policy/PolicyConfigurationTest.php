<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Tests\Policy;

use Vos\LabelClassifier\Policy\LabelApplication;
use Vos\LabelClassifier\Policy\LabelPolicy;
use Vos\LabelClassifier\Policy\PolicyConfiguration;
use Vos\LabelClassifier\Policy\Probability;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PolicyConfigurationTest extends TestCase
{
    public function testCanonicalConfigurationLoadsEveryLabel(): void
    {
        $configuration = PolicyConfiguration::fromFile($this->canonicalConfigPath());

        self::assertSame(10, $configuration->count());
        self::assertContains('Bug', $configuration->labelNames());
        self::assertContains('dependencies', $configuration->labelNames());
    }

    public function testNeedsTestIsAdvisoryAndReleaseBlockerIsManual(): void
    {
        $policies = PolicyConfiguration::fromFile($this->canonicalConfigPath())->all();

        $byLabel = [];

        foreach ($policies as $policy) {
            $byLabel[$policy->label] = $policy;
        }

        self::assertSame(LabelApplication::Advisory, $byLabel['Needs Test']->application);
        self::assertSame(LabelApplication::Manual, $byLabel['Release Blocker']->application);
        self::assertSame(LabelApplication::Auto, $byLabel['Bug']->application);
    }

    public function testThresholdOverridesOnlyTouchTheNamedLabels(): void
    {
        $configuration = PolicyConfiguration::fromFile(
            $this->canonicalConfigPath(),
            thresholdOverrides: 'Bug=0.95',
        );

        $configured = json_decode((string) file_get_contents($this->canonicalConfigPath()), true, flags: JSON_THROW_ON_ERROR);

        foreach ($configuration->all() as $policy) {
            if ($policy->label === 'Bug') {
                self::assertSame(0.95, $policy->threshold->value);

                continue;
            }

            self::assertSame((float) $configured['labels'][$policy->label]['threshold'], $policy->threshold->value);
        }
    }

    public function testOverrideForUnconfiguredLabelIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unconfigured label: Nope');

        PolicyConfiguration::fromFile($this->canonicalConfigPath(), thresholdOverrides: 'Nope=0.9');
    }

    public function testDirectConstructionKeepsTheGivenPolicies(): void
    {
        $configuration = PolicyConfiguration::fromPolicies([
            new LabelPolicy('Bug', 'Is this a bug fix?', Probability::fromFloat(0.9), LabelApplication::Auto),
        ]);

        self::assertSame(['Bug'], $configuration->labelNames());
    }

    private function canonicalConfigPath(): string
    {
        return dirname(__DIR__, 2) . '/config/labels.json';
    }
}
