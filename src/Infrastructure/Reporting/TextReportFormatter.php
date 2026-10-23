<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Infrastructure\Reporting;

use PhpRuntimeAudit\Application\ReportFormatter;
use PhpRuntimeAudit\Domain\Assessment;

final class TextReportFormatter implements ReportFormatter
{
    /**
     * @param list<Assessment> $assessments
     */
    public function format(array $assessments): string
    {
        if ($assessments === []) {
            return 'No PHP image references found.';
        }

        $lines = [];

        foreach ($assessments as $assessment) {
            $advisories = $assessment->advisoryIds === []
                ? ''
                : sprintf(
                    ' [%s]',
                    implode(', ', array_map($this->sanitizeTerminalControls(...), $assessment->advisoryIds))
                );

            $lines[] = sprintf(
                '%s:%s: %s %s%s - %s',
                $this->sanitizeTerminalControls($assessment->reference->file),
                $assessment->reference->line ?? '?',
                strtoupper(str_replace('_', ' ', $assessment->status->value)),
                $this->sanitizeTerminalControls($assessment->reference->image),
                $advisories,
                $this->sanitizeTerminalControls($assessment->reason),
            );
        }

        return implode(PHP_EOL, $lines);
    }

    private function sanitizeTerminalControls(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]|\xC2[\x80-\x9F]/', '?', $value) ?? '';
    }
}
