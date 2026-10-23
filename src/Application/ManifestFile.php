<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Application;

final readonly class ManifestFile
{
    public function __construct(
        public string $path,
        public string $contents,
    ) {}
}
