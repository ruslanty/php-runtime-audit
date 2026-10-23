<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Domain;

final readonly class ImageReference
{
    public function __construct(
        public string $file,
        public ?int $line,
        public string $image,
    ) {}
}
