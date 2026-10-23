<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Infrastructure\Reporting;

use PhpRuntimeAudit\Application\ReportFormatter;
use PhpRuntimeAudit\Domain\Assessment;

final class JsonReportFormatter implements ReportFormatter
{
    /**
     * @param list<Assessment> $assessments
     *
     * @throws \JsonException
     */
    public function format(array $assessments): string
    {
        $payload = [
            'schema_version' => 1,
            'assessments' => array_map(
                static fn(Assessment $assessment): array => [
                    'file' => $assessment->reference->file,
                    'line' => $assessment->reference->line,
                    'image' => $assessment->reference->image,
                    'php_version' => $assessment->phpVersion,
                    'status' => $assessment->status->value,
                    'reason' => $assessment->reason,
                    'advisory_url' => $assessment->advisoryUrl,
                    'advisory_ids' => $assessment->advisoryIds,
                ],
                $assessments,
            ),
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );
    }
}
