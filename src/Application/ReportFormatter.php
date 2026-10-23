<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Application;

use PhpRuntimeAudit\Domain\Assessment;

interface ReportFormatter
{
    /**
     * @param list<Assessment> $assessments
     */
    public function format(array $assessments): string;
}
