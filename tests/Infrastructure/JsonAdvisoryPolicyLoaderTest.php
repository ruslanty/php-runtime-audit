<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Tests\Infrastructure;

use PhpRuntimeAudit\Infrastructure\Advisory\JsonAdvisoryPolicyLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonAdvisoryPolicyLoaderTest extends TestCase
{
    public function testItRejectsCatalogsWithMultiplePoliciesInsteadOfSilentlyIgnoringEntries(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'php-runtime-audit-advisories-');

        self::assertNotFalse($path);

        try {
            file_put_contents($path, json_encode([
                'advisories' => [
                    ['id' => 'CVE-2026-0001'],
                    ['id' => 'CVE-2026-0002'],
                ],
            ], JSON_THROW_ON_ERROR));

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('exactly one supported policy entry');

            new JsonAdvisoryPolicyLoader()->load($path);
        } finally {
            unlink($path);
        }
    }
}
