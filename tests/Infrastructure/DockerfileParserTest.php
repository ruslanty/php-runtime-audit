<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Tests\Infrastructure;

use PhpRuntimeAudit\Application\ManifestFile;
use PhpRuntimeAudit\Infrastructure\Parser\DockerfileParser;
use PHPUnit\Framework\TestCase;

final class DockerfileParserTest extends TestCase
{
    public function testItReadsBuildStagesAndPlatformOptions(): void
    {
        $parser = new DockerfileParser();
        $file = new ManifestFile('Dockerfile', implode(PHP_EOL, [
            'FROM --platform=linux/amd64 php:8.4.26-fpm AS build',
            'FROM alpine:3.22',
            '',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame('php:8.4.26-fpm', $references[0]->image);
        self::assertSame(1, $references[0]->line);
    }

    public function testItJoinsContinuedFromInstructions(): void
    {
        $parser = new DockerfileParser();
        $file = new ManifestFile('Dockerfile', implode(PHP_EOL, [
            'FROM ' . '\\',
            '  --platform=linux/amd64 php:8.5.11-cli AS app',
            '',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame('php:8.5.11-cli', $references[0]->image);
        self::assertSame(1, $references[0]->line);
    }

    public function testItKeepsGlobalBuildArgumentReferencesUnknownEvenWhenDefaultsExist(): void
    {
        $parser = new DockerfileParser();
        $file = new ManifestFile('Dockerfile', implode(PHP_EOL, [
            'ARG PHP_VERSION=8.4.26',
            'ARG BASE_IMAGE=php:$' . '{PHP_VERSION}-fpm',
            'FROM $' . '{BASE_IMAGE}',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame('$' . '{BASE_IMAGE}', $references[0]->image);
        self::assertSame(3, $references[0]->line);
    }

    public function testItReturnsUnresolvedFromArgumentsForUnknownAssessment(): void
    {
        $parser = new DockerfileParser();
        $file = new ManifestFile('Dockerfile', 'FROM $' . '{BASE_IMAGE}');

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame('$' . '{BASE_IMAGE}', $references[0]->image);
    }

    public function testItUsesTheBacktickEscapeDirectiveForContinuedFromInstructions(): void
    {
        $escapeToken = chr(96);
        $parser = new DockerfileParser();
        $file = new ManifestFile('Dockerfile', implode(PHP_EOL, [
            '# syntax=docker/dockerfile:1',
            '# escape=' . $escapeToken,
            'FROM --platform=linux/amd64 ' . $escapeToken,
            '    php:8.5.11-cli',
        ]));

        $references = $parser->parse($file);

        self::assertCount(1, $references);
        self::assertSame('php:8.5.11-cli', $references[0]->image);
        self::assertSame(3, $references[0]->line);
    }
}
