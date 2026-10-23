<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Tests\Infrastructure;

use PhpRuntimeAudit\Infrastructure\Filesystem\RecursiveManifestDiscovery;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RecursiveManifestDiscoveryTest extends TestCase
{
    public function testItDoesNotDiscoverManifestsInsideIgnoredDirectories(): void
    {
        $root = sys_get_temp_dir() . '/php-runtime-audit-' . bin2hex(random_bytes(8));

        mkdir($root . '/vendor/package', 0o777, true);

        file_put_contents($root . '/Dockerfile', 'FROM php:8.5.12-fpm');
        file_put_contents($root . '/vendor/package/compose.yaml', 'image: php:8.5.11-fpm');

        try {
            $files = new RecursiveManifestDiscovery()->find($root);

            self::assertCount(1, $files);
            self::assertSame('Dockerfile', $files[0]->path);
        } finally {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $entry) {
                if (! $entry instanceof SplFileInfo) {
                    continue;
                }

                if ($entry->isDir()) {
                    rmdir($entry->getPathname());

                    continue;
                }

                unlink($entry->getPathname());
            }

            rmdir($root);
        }
    }
}
