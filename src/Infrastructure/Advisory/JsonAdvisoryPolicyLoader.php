<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Infrastructure\Advisory;

use InvalidArgumentException;
use JsonException;
use PhpRuntimeAudit\Domain\UpstreamVersionPolicy;
use RuntimeException;

final class JsonAdvisoryPolicyLoader
{
    public function load(string $path): UpstreamVersionPolicy
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Could not read advisory catalog: %s', $path));
        }

        try {
            $catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('Invalid advisory catalog JSON: %s', $path), 0, $jsonException);
        }

        $advisories = is_array($catalog)
            ? ($catalog['advisories'] ?? null)
            : null;

        if (! is_array($advisories)
            || count($advisories) !== 1
            || ! is_array($advisories[0] ?? null)
        ) {
            throw new RuntimeException(
                sprintf('Advisory catalog must contain exactly one supported policy entry: %s', $path)
            );
        }

        $advisory = $advisories[0];
        $source = $advisory['source'] ?? null;
        $id = $advisory['id'] ?? null;
        $relatedId = $advisory['related_id'] ?? null;
        $fixedReleases = $advisory['fixed_releases'] ?? null;
        $unsupportedBranches = $advisory['unsupported_branches'] ?? null;

        if (! is_string($source)
            || ! is_string($id)
            || ($relatedId !== null && ! is_string($relatedId))
            || ! is_array($fixedReleases)
            || ! is_array($unsupportedBranches)
        ) {
            throw new RuntimeException(sprintf('Advisory catalog entry is incomplete: %s', $path));
        }

        $advisoryIds = [$id];

        if (is_string($relatedId)) {
            $advisoryIds[] = $relatedId;
        }

        try {
            return new UpstreamVersionPolicy($fixedReleases, $unsupportedBranches, $source, $advisoryIds);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new RuntimeException(
                sprintf('Invalid advisory policy in catalog: %s', $path),
                0,
                $invalidArgumentException
            );
        }
    }
}
