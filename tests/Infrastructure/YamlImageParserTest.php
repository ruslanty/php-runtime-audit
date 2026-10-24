<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Tests\Infrastructure;

use PhpRuntimeAudit\Application\ManifestFile;
use PhpRuntimeAudit\Domain\ImageReference;
use PhpRuntimeAudit\Infrastructure\Parser\YamlImageParser;
use PHPUnit\Framework\TestCase;

final class YamlImageParserTest extends TestCase
{
    public function testItFindsImagesInYamlManifestsWithSourceLines(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('compose.yaml', implode(PHP_EOL, [
            'services:',
            '  app:',
            '    image: php:8.4.26-fpm',
            '',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame('php:8.4.26-fpm', $references[0]->image);
        self::assertSame(3, $references[0]->line);
    }

    public function testItFindsImagesAcrossMultipleYamlDocumentsWithSourceLines(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('application.yaml', implode(PHP_EOL, [
            '---',
            'services:',
            '  first:',
            '    image: php:8.4.26-fpm',
            '---',
            'services:',
            '  second:',
            '    image: php:8.5.11-fpm',
        ]));

        $references = $parser->parse($file);

        self::assertCount(2, $references);
        self::assertSame(
            [
                'php:8.4.26-fpm',
                'php:8.5.11-fpm',
            ],
            array_map(
                static fn(ImageReference $reference): string => $reference->image,
                $references,
            )
        );
        self::assertSame(
            [4, 8],
            array_map(
                static fn(ImageReference $reference): ?int => $reference->line,
                $references,
            )
        );
    }

    public function testItFindsImagesAcrossDocumentsWithInlineStartMarkers(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('application.yaml', implode(PHP_EOL, [
            '--- {services: {first: {image: php:8.4.26-fpm}}}',
            '--- {services: {second: {image: php:8.5.11-fpm}}}',
        ]));

        $references = $parser->parse($file);

        self::assertCount(2, $references);
        self::assertSame(
            [
                'php:8.4.26-fpm',
                'php:8.5.11-fpm',
            ],
            array_map(
                static fn(ImageReference $reference): string => $reference->image,
                $references,
            )
        );
        self::assertSame(
            [1, 2],
            array_map(
                static fn(ImageReference $reference): ?int => $reference->line,
                $references,
            )
        );
    }

    public function testItFindsImagesInYamlFlowMappings(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('compose.yaml', 'services: {app: {image: php:8.5.11-fpm}}');

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame('php:8.5.11-fpm', $references[0]->image);
        self::assertSame(1, $references[0]->line);
    }

    public function testItReturnsUnresolvedComposeImageVariablesForUnknownAssessment(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('compose.yaml', implode(PHP_EOL, [
            'services:',
            '  app:',
            '    image: ${IMAGE:-php:8.5.11-fpm}',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame('${IMAGE:-php:8.5.11-fpm}', $references[0]->image);
        self::assertNull($references[0]->line);
    }

    public function testItLocatesMultipleImagesInOneYamlFlowMappingLine(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile(
            'compose.yaml',
            'services: {first: {image: php:8.4.26-fpm}, second: {image: php:8.5.11-fpm}}',
        );

        $references = $parser->parse($file);

        self::assertCount(2, $references);
        self::assertSame(
            [1, 1],
            array_map(
                static fn(ImageReference $reference): ?int => $reference->line,
                $references,
            )
        );
    }

    public function testItDoesNotTreatTextInsideYamlBlockScalarsAsImageKeys(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('compose.yaml', implode(PHP_EOL, [
            'notes: |',
            '  image: php:8.5.11-fpm',
        ]));

        self::assertSame([], $parser->parse($file));
    }

    public function testItKeepsTheSourceLineForAnchoredImageValues(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('compose.yaml', implode(PHP_EOL, [
            'services:',
            '  app:',
            '    image: &php php:8.5.11-fpm',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame('php:8.5.11-fpm', $references[0]->image);
        self::assertSame(3, $references[0]->line);
    }

    public function testItResolvesAliasesAndReportsTheImageUseLine(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('compose.yaml', implode(PHP_EOL, [
            'base: &php php:8.5.11-fpm',
            'services:',
            '  app:',
            '    image: *php',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame(4, $references[0]->line);
        self::assertSame('php:8.5.11-fpm', $references[0]->image);
    }

    public function testItReportsTheKeyLineForTaggedScalarValues(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('compose.yaml', implode(PHP_EOL, [
            'services:',
            '  app:',
            '    image: !!str php:8.5.11-fpm',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame(3, $references[0]->line);
    }

    public function testItDoesNotInventALineWhenTheYamlKeyFormCannotBeMapped(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('compose.yaml', implode(PHP_EOL, [
            'services:',
            '  app:',
            '    !!str image: php:8.5.11-fpm',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertNull($references[0]->line);
    }

    public function testItDoesNotReportImageTextInsideBlockScalars(): void
    {
        $parser = new YamlImageParser();
        $file = new ManifestFile('compose.yaml', implode(PHP_EOL, [
            'notes: |',
            '  image: php:8.5.11-fpm',
            'services:',
            '  app:',
            '    image: php:8.5.11-fpm',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame(5, $references[0]->line);
    }
}
