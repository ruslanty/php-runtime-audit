<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Infrastructure\Parser;

use PhpRuntimeAudit\Application\ManifestFile;
use PhpRuntimeAudit\Application\ManifestParser;
use PhpRuntimeAudit\Domain\ImageReference;

final readonly class DockerfileParser implements ManifestParser
{
    public function __construct(private PhpRuntimeImageMatcher $matcher = new PhpRuntimeImageMatcher()) {}

    public function supports(string $path): bool
    {
        return preg_match('~(?:^|\/)Dockerfile(?:\..+)?$~i', $path) === 1;
    }

    public function parse(ManifestFile $file): array
    {
        $references = [];
        $logicalLine = '';
        $logicalStart = 0;
        $escapeToken = '\\';
        $parserDirectivesAllowed = true;
        $lines = preg_split('~\R~', $file->contents);

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $index => $line) {
            if ($logicalLine === '') {
                $logicalStart = $index + 1;

                if ($parserDirectivesAllowed) {
                    $trimmedLine = trim($line);

                    if ($trimmedLine === '') {
                        $parserDirectivesAllowed = false;

                        continue;
                    }

                    if (str_starts_with($trimmedLine, '#')) {
                        if (preg_match('~^#\s*(?:syntax|escape|check)\s*=~i', $trimmedLine) !== 1) {
                            $parserDirectivesAllowed = false;
                        } else {
                            $escapeToken = $this->escapeTokenFromDirective($trimmedLine) ?? $escapeToken;
                        }

                        continue;
                    }

                    $parserDirectivesAllowed = false;
                }
            }

            $trimmedLine = rtrim($line);
            $continued = str_ends_with($trimmedLine, $escapeToken);
            $lineContent = $continued
                ? substr($trimmedLine, 0, -strlen($escapeToken))
                : $line;

            $logicalLine .= ' ' . $lineContent;

            if ($continued) {
                continue;
            }

            $reference = $this->parseFrom(
                $logicalLine,
                $file->path,
                $logicalStart,
            );

            if ($reference instanceof ImageReference) {
                $references[] = $reference;
            }

            $logicalLine = '';
        }

        if ($logicalLine !== '') {
            $reference = $this->parseFrom(
                $logicalLine,
                $file->path,
                $logicalStart,
            );

            if ($reference instanceof ImageReference) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    private function escapeTokenFromDirective(string $line): ?string
    {
        if (preg_match('~^#\s*escape\s*=\s*(?<escape>.+)$~i', $line, $matches) !== 1) {
            return null;
        }

        $escapeToken = trim($matches['escape']);

        return $escapeToken === '\\' || $escapeToken === chr(96)
            ? $escapeToken
            : null;
    }

    private function parseFrom(
        string $line,
        string $path,
        int $lineNumber,
    ): ?ImageReference {
        $line = trim((string) preg_replace('~\s+#.*$~', '', $line));

        if (preg_match('~^FROM\s+(.+)$~i', $line, $matches) !== 1) {
            return null;
        }

        $parts = preg_split('~\s+~', trim($matches[1]));

        if ($parts === false) {
            return null;
        }

        while ($parts !== []
            && str_starts_with($parts[0], '--')
        ) {
            array_shift($parts);
        }

        $image = $parts[0] ?? null;

        if ($image === null
            || $image === ''
        ) {
            return null;
        }

        $hasVariable = preg_match('~\$(?:[A-Za-z_{])~', $image) === 1;

        if (! $hasVariable
            && ! $this->matcher->matches($image)
        ) {
            return null;
        }

        return new ImageReference($path, $lineNumber, $image);
    }
}
