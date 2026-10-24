<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Infrastructure\Parser;

use PhpRuntimeAudit\Application\ManifestFile;
use PhpRuntimeAudit\Application\ManifestParser;
use PhpRuntimeAudit\Domain\ImageReference;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlImageParser implements ManifestParser
{
    public function __construct(
        private PhpRuntimeImageMatcher $matcher = new PhpRuntimeImageMatcher(),
        private YamlSourceLineLocator $sourceLineLocator = new YamlSourceLineLocator(),
    ) {}

    public function supports(string $path): bool
    {
        return preg_match('~\.ya?ml$~i', $path) === 1;
    }

    public function parse(ManifestFile $file): array
    {
        $references = [];
        $usedPositions = [];

        $documents = preg_split(
            '~(?m)^---(?:[ \t]+(?=[^# \t\r\n])|[ \t]+(?:\#[^\r\n]*)?(?:\R|\z)|(?:\R|\z))~',
            $file->contents,
            -1,
            PREG_SPLIT_OFFSET_CAPTURE,
        );

        if ($documents === false) {
            throw new RuntimeException(sprintf('Could not split YAML documents in %s.', $file->path));
        }

        foreach ($documents as [$contents, $offset]) {
            $lineOffset = substr_count($file->contents, chr(10), 0, $offset);

            try {
                $document = Yaml::parse($contents);
            } catch (ParseException $parseException) {
                $parsedLine = $parseException->getParsedLine();

                throw new ParseException(
                    sprintf('Invalid YAML in %s: %s', $file->path, $parseException->getMessage()),
                    $parsedLine > 0
                        ? $parsedLine + $lineOffset
                        : $parsedLine,
                    $parseException->getSnippet(),
                    $file->path,
                    $parseException,
                );
            }

            foreach ($this->imageValues($document) as $image) {
                if (! $this->matcher->matches($image)
                    && preg_match('~\$(?:[A-Za-z_{])~', $image) !== 1
                ) {
                    continue;
                }

                $line = $this->sourceLineLocator->locate($file->contents, $image, $usedPositions);

                $references[] = new ImageReference($file->path, $line, $image);
            }
        }

        return $references;
    }

    /**
     * @return list<string>
     */
    private function imageValues(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $images = [];

        foreach ($node as $key => $value) {
            if (is_string($key)
                && strtolower($key) === 'image'
                && is_string($value)
            ) {
                $images[] = $value;

                continue;
            }

            array_push($images, ...$this->imageValues($value));
        }

        return $images;
    }
}
