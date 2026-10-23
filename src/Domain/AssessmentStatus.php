<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Domain;

enum AssessmentStatus: string
{
    case Ok = 'ok';
    case PotentiallyVulnerable = 'potentially_vulnerable';
    case Unknown = 'unknown';
}
