<?php

declare(strict_types=1);

namespace Vos\LabelClassifier\Benchmark;

final class BenchmarkReporter
{
    /** @return list<string> */
    public function lines(BenchmarkReport $report): array
    {
        $lines = [];
        $lines[] = sprintf(
            'Benchmark over %d pull requests (frozen merged state, not the opening state).',
            $report->caseCount(),
        );
        $lines[] = '';
        $lines[] = 'Label        precision  recall    tp   fp   fn   expected  abstained';

        foreach ($report->metrics() as $label => $metrics) {
            $lines[] = sprintf(
                '%-12s %-10s %-9s %4d %4d %4d %9d %10d',
                $this->truncate($label),
                $this->ratio($metrics->precision()),
                $this->ratio($metrics->recall()),
                $metrics->truePositives(),
                $metrics->falsePositives(),
                $metrics->falseNegatives(),
                $metrics->expectedCount(),
                $metrics->abstainedCount(),
            );
        }

        $lines[] = '';
        $model = $report->resolvedModel();
        $lines[] = sprintf('Resolved model: %s', $model ?? 'not reported');
        $lines[] = sprintf('Total latency: %.1f ms', $report->totalLatencyMilliseconds());

        foreach ($report->usageTotals() as $field => $total) {
            $lines[] = sprintf('Usage %s: %s', $field, $this->usageText($total));
        }

        return $lines;
    }

    private function usageText(int|float $total): string
    {
        if (abs($total) < 1) {
            return sprintf('%.4f', $total);
        }

        return number_format((float) $total);
    }

    private function truncate(string $label): string
    {
        return mb_strlen($label) > 12 ? mb_substr($label, 0, 11) . '…' : $label;
    }

    private function ratio(?float $value): string
    {
        return $value === null ? '-' : sprintf('%.2f', $value);
    }
}
