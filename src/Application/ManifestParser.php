<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Application;

use PhpRuntimeAudit\Domain\ImageReference;

interface ManifestParser
{
    public function supports(string $path): bool;

    /**
     * @return list<ImageReference>
     */
    public function parse(ManifestFile $file): array;
}
