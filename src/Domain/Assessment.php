<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Domain;

final readonly class Assessment
{
    public function __construct(
        public ImageReference $reference,
        public AssessmentStatus $status,
        public string $reason,
        public ?string $phpVersion = null,
        public ?string $advisoryUrl = null,
        /** @var list<string> */
        public array $advisoryIds = [],
    ) {}
}
