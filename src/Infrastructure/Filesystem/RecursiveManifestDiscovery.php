<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Infrastructure\Filesystem;

use FilesystemIterator;
use PhpRuntimeAudit\Application\ManifestDiscovery;
use PhpRuntimeAudit\Application\ManifestFile;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class RecursiveManifestDiscovery implements ManifestDiscovery
{
    private const array IGNORED_DIRECTORIES = [
        '.git',
        'vendor',
        'node_modules',
    ];

    public function find(string $root): array
    {
        $path = realpath($root);

        if ($path === false
            || ! is_dir($path)
            || ! is_readable($path)
        ) {
            throw new RuntimeException(sprintf('Scan path is not a readable directory: %s', $root));
        }

        $files = [];

        $directoryIterator = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);

        $filteredIterator = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            static fn(SplFileInfo $entry): bool => ! $entry->isDir()
                || ! in_array($entry->getFilename(), self::IGNORED_DIRECTORIES, true),
        );

        $iterator = new RecursiveIteratorIterator(
            $filteredIterator,
        );

        foreach ($iterator as $entry) {
            if (! $entry instanceof SplFileInfo
                || ! $entry->isFile()
                || $entry->isLink()
                || ! $this->isSupported($entry->getFilename())
            ) {
                continue;
            }

            $contents = file_get_contents($entry->getPathname());

            if ($contents === false) {
                throw new RuntimeException(sprintf('Could not read manifest: %s', $entry->getPathname()));
            }

            $files[] = new ManifestFile(
                str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    ltrim(substr($entry->getPathname(), strlen($path)), DIRECTORY_SEPARATOR),
                ),
                $contents,
            );
        }

        usort($files, static fn(ManifestFile $left, ManifestFile $right): int => $left->path <=> $right->path);

        return $files;
    }

    private function isSupported(string $filename): bool
    {
        return preg_match('~^Dockerfile(?:\..+)?$~i', $filename) === 1
            || preg_match('~\.(?:ya?ml)$~i', $filename) === 1;
    }
}
