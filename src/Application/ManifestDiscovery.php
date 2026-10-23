<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Application;

interface ManifestDiscovery
{
    /**
     * @return list<ManifestFile>
     */
    public function find(string $root): array;
}
