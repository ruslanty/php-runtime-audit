<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Tests\Presentation;

use PhpRuntimeAudit\Application\ScanProject;
use PhpRuntimeAudit\Domain\Assessment;
use PhpRuntimeAudit\Domain\AssessmentStatus;
use PhpRuntimeAudit\Domain\ImageReference;
use PhpRuntimeAudit\Infrastructure\Advisory\JsonAdvisoryPolicyLoader;
use PhpRuntimeAudit\Infrastructure\Filesystem\RecursiveManifestDiscovery;
use PhpRuntimeAudit\Infrastructure\Parser\DockerfileParser;
use PhpRuntimeAudit\Infrastructure\Parser\YamlImageParser;
use PhpRuntimeAudit\Infrastructure\Reporting\JsonReportFormatter;
use PhpRuntimeAudit\Infrastructure\Reporting\TextReportFormatter;
use PhpRuntimeAudit\Presentation\ScanCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ScanCommandTest extends TestCase
{
    public function testItReturnsOneAndReportsAnAffectedImage(): void
    {
        $tester = new CommandTester($this->command());
        $tester->execute(['path' => __DIR__ . '/../Fixtures/affected']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('POTENTIALLY VULNERABLE', $tester->getDisplay());
        self::assertStringContainsString('Dockerfile:1', $tester->getDisplay());
    }

    public function testItReturnsThreeForUnassessableImageTags(): void
    {
        $tester = new CommandTester($this->command());
        $tester->execute([
            'path' => __DIR__ . '/../Fixtures/unknown',
            '--format' => 'json',
        ]);

        self::assertSame(3, $tester->getStatusCode());
        self::assertStringContainsString('"status": "unknown"', $tester->getDisplay());
    }

    public function testItReturnsTwoWhenTheScanPathIsInvalid(): void
    {
        $tester = new CommandTester($this->command());
        $tester->execute(['path' => __DIR__ . '/missing-directory']);

        self::assertSame(2, $tester->getStatusCode());
    }

    public function testItDoesNotTreatAnUnresolvedFromArgumentAsAResolvedCleanScan(): void
    {
        $root = sys_get_temp_dir() . '/php-runtime-audit-' . bin2hex(random_bytes(8));

        mkdir($root);

        file_put_contents($root . '/Dockerfile', 'FROM $' . '{BASE_IMAGE}');

        try {
            $tester = new CommandTester($this->command());
            $tester->execute(['path' => $root]);

            self::assertSame(3, $tester->getStatusCode());
            self::assertStringContainsString('UNKNOWN', $tester->getDisplay());
        } finally {
            unlink($root . '/Dockerfile');

            rmdir($root);
        }
    }

    public function testItReturnsUnknownForFromArgumentEvenWhenADefaultExists(): void
    {
        $root = sys_get_temp_dir() . '/php-runtime-audit-' . bin2hex(random_bytes(8));

        mkdir($root);

        file_put_contents($root . '/Dockerfile', implode(PHP_EOL, [
            'ARG BASE_IMAGE=php:8.4.26-fpm',
            'FROM $' . '{BASE_IMAGE}',
        ]));

        try {
            $tester = new CommandTester($this->command());
            $tester->execute(['path' => $root]);

            self::assertSame(3, $tester->getStatusCode());
            self::assertStringContainsString('UNKNOWN', $tester->getDisplay());
        } finally {
            unlink($root . '/Dockerfile');

            rmdir($root);
        }
    }

    public function testItReturnsThreeForVariableBasedYamlImageReferences(): void
    {
        $root = sys_get_temp_dir() . '/php-runtime-audit-' . bin2hex(random_bytes(8));

        mkdir($root);

        file_put_contents($root . '/compose.yaml', implode(PHP_EOL, [
            'services:',
            '  app:',
            '    image: ${IMAGE:-php:8.5.11-fpm}',
        ]));

        try {
            $tester = new CommandTester($this->command());
            $tester->execute(['path' => $root]);

            self::assertSame(3, $tester->getStatusCode());
            self::assertStringContainsString('UNKNOWN', $tester->getDisplay());
        } finally {
            unlink($root . '/compose.yaml');

            rmdir($root);
        }
    }

    public function testJsonReportSubstitutesInvalidUtf8FromManifestPaths(): void
    {
        $assessment = new Assessment(
            new ImageReference('compose' . chr(255) . '.yaml', 1, 'php:8.5.11-fpm'),
            AssessmentStatus::PotentiallyVulnerable,
            'Image tag is below the listed upstream fixed release.',
        );

        $json = new JsonReportFormatter()->format([$assessment]);
        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($report);
        self::assertIsArray($report['assessments'] ?? null);
        self::assertIsArray($report['assessments'][0] ?? null);
        self::assertSame('compose�.yaml', $report['assessments'][0]['file'] ?? null);
    }

    public function testTextOutputDoesNotPassThroughTerminalControlCharactersFromAnImage(): void
    {
        $root = sys_get_temp_dir() . '/php-runtime-audit-' . bin2hex(random_bytes(8));

        mkdir($root);

        file_put_contents($root . '/compose.yaml', implode(PHP_EOL, [
            'services:',
            '  app:',
            '    image: "php:8.4.26-fpm\u001b[2J"',
        ]));

        try {
            $tester = new CommandTester($this->command());
            $tester->execute(['path' => $root]);

            self::assertSame(1, $tester->getStatusCode());
            self::assertStringNotContainsString(chr(27), $tester->getDisplay());
        } finally {
            unlink($root . '/compose.yaml');

            rmdir($root);
        }
    }

    public function testScanErrorsDoNotPassThroughTerminalControlCharactersFromThePath(): void
    {
        $tester = new CommandTester($this->command());
        $tester->execute(['path' => chr(27) . '[2J/missing-directory']);

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringNotContainsString(chr(27), $tester->getDisplay());
    }

    public function testJsonReportContainsAdvisoryIdentifiers(): void
    {
        $tester = new CommandTester($this->command());
        $tester->execute([
            'path' => __DIR__ . '/../Fixtures/affected',
            '--format' => 'json',
        ]);

        self::assertStringContainsString('"advisory_ids": [', $tester->getDisplay());
        self::assertStringContainsString('CVE-2026-109618', $tester->getDisplay());
        self::assertStringContainsString('CVE-2026-109619', $tester->getDisplay());
    }

    public function testJsonOutputRemainsValidWhenManifestPathContainsConsoleMarkup(): void
    {
        $root = sys_get_temp_dir() . '/php-runtime-audit-' . bin2hex(random_bytes(8));

        mkdir($root);

        file_put_contents($root . '/<error>.yaml', 'image: php:8.5.11-fpm');

        try {
            $tester = new CommandTester($this->command());
            $tester->execute(['path' => $root, '--format' => 'json']);

            self::assertSame(1, $tester->getStatusCode());

            $report = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

            self::assertIsArray($report);
            self::assertIsArray($report['assessments'] ?? null);
            self::assertIsArray($report['assessments'][0] ?? null);
            self::assertSame('<error>.yaml', $report['assessments'][0]['file'] ?? null);
        } finally {
            unlink($root . '/<error>.yaml');

            rmdir($root);
        }
    }

    public function testJsonReportUsesNullWhenYamlSourceLineCannotBeMapped(): void
    {
        $root = sys_get_temp_dir() . '/php-runtime-audit-' . bin2hex(random_bytes(8));

        mkdir($root);

        file_put_contents($root . '/compose.yaml', "services:\n  app:\n    !!str image: php:8.5.11-fpm\n");

        try {
            $tester = new CommandTester($this->command());
            $tester->execute(['path' => $root, '--format' => 'json']);

            $report = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

            self::assertIsArray($report);
            self::assertIsArray($report['assessments'] ?? null);
            self::assertIsArray($report['assessments'][0] ?? null);
            self::assertNull($report['assessments'][0]['line'] ?? null);
        } finally {
            unlink($root . '/compose.yaml');

            rmdir($root);
        }
    }

    private function command(): ScanCommand
    {
        $policy = new JsonAdvisoryPolicyLoader()->load(dirname(__DIR__, 2) . '/data/advisories.json');
        $scanner = new ScanProject(
            new RecursiveManifestDiscovery(),
            [new DockerfileParser(), new YamlImageParser()],
            $policy,
        );

        return new ScanCommand($scanner, [
            'text' => new TextReportFormatter(),
            'json' => new JsonReportFormatter(),
        ]);
    }
}
