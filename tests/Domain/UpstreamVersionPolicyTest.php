<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Tests\Domain;

use PhpRuntimeAudit\Domain\AssessmentStatus;
use PhpRuntimeAudit\Domain\ImageReference;
use PhpRuntimeAudit\Domain\UpstreamVersionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpstreamVersionPolicyTest extends TestCase
{
    #[DataProvider('versionCases')]
    public function testItAssessesOnlyKnownUpstreamVersionBranches(string $image, AssessmentStatus $expected): void
    {
        $policy = $this->policy();

        self::assertSame($expected, $policy->assess(new ImageReference('Dockerfile', 1, $image))->status);
    }

    /** @return iterable<string, array{string, AssessmentStatus}> */
    public static function versionCases(): iterable
    {
        yield 'affected upstream version' => [
            'php:8.4.26-fpm',
            AssessmentStatus::PotentiallyVulnerable,
        ];

        yield 'upstream fixed version' => [
            'php:8.4.27-fpm-alpine',
            AssessmentStatus::Ok,
        ];

        yield 'unsupported old branch' => [
            'php:8.1.99-fpm',
            AssessmentStatus::PotentiallyVulnerable,
        ];

        yield 'floating tag' => [
            'php:latest',
            AssessmentStatus::Unknown,
        ];

        yield 'version without patch' => [
            'php:8.4-fpm',
            AssessmentStatus::Unknown,
        ];

        yield 'unknown future branch' => [
            'php:9.0.1-fpm',
            AssessmentStatus::Unknown,
        ];

        yield 'custom image is not an official tag' => [
            'registry.example/php:8.4.26',
            AssessmentStatus::Unknown,
        ];

        yield 'release candidate is not a stable fixed release' => [
            'php:8.5.12-rc1-fpm',
            AssessmentStatus::Unknown,
        ];

        yield 'beta is not a stable fixed release' => [
            'php:8.5.12-beta2-fpm',
            AssessmentStatus::Unknown,
        ];
    }

    public function testItCarriesAllAdvisoryIdentifiers(): void
    {
        $assessment = $this->policy()->assess(new ImageReference('Dockerfile', 1, 'php:8.5.11-fpm'));

        self::assertSame(['CVE-2026-109618', 'CVE-2026-109619'], $assessment->advisoryIds);
    }

    private function policy(): UpstreamVersionPolicy
    {
        return new UpstreamVersionPolicy(
            ['8.4' => '8.4.27'],
            ['8.1'],
            'https://www.php.net/archive/2026.php#2026-10-21-5',
            ['CVE-2026-109618', 'CVE-2026-109619'],
        );
    }
}
