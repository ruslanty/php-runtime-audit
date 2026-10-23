<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Domain;

use InvalidArgumentException;

final readonly class UpstreamVersionPolicy
{
    /** @var array<string, string> */
    private array $fixedReleases;

    /** @var list<string> */
    private array $unsupportedBranches;

    private string $advisoryUrl;

    /** @var list<string> */
    private array $advisoryIds;

    /**
     * @param array<array-key, mixed> $fixedReleases
     * @param array<array-key, mixed> $unsupportedBranches
     * @param array<array-key, mixed> $advisoryIds
     */
    public function __construct(
        array $fixedReleases,
        array $unsupportedBranches,
        string $advisoryUrl,
        array $advisoryIds = [],
    ) {
        if ($fixedReleases === []) {
            throw new InvalidArgumentException('At least one fixed release is required.');
        }

        $validatedFixedReleases = [];

        foreach ($fixedReleases as $branch => $version) {
            if (! is_string($branch)
                || ! is_string($version)
                || preg_match('~^\d+\.\d+$~', $branch) !== 1
                || preg_match('~^\d+\.\d+\.\d+$~', $version) !== 1
                || ! str_starts_with($version, $branch . '.')
            ) {
                throw new InvalidArgumentException('Fixed releases must map valid branches to matching versions.');
            }

            $validatedFixedReleases[$branch] = $version;
        }

        if (! array_is_list($unsupportedBranches)) {
            throw new InvalidArgumentException('Unsupported branches must be a list.');
        }

        $validatedUnsupportedBranches = [];

        foreach ($unsupportedBranches as $branch) {
            if (! is_string($branch)
                || preg_match('~^\d+\.\d+$~', $branch) !== 1
                || array_key_exists($branch, $validatedFixedReleases)
                || in_array($branch, $validatedUnsupportedBranches, true)
            ) {
                throw new InvalidArgumentException(
                    'Unsupported branches must be valid, unique, and have no fixed release.'
                );
            }

            $validatedUnsupportedBranches[] = $branch;
        }

        if (filter_var($advisoryUrl, FILTER_VALIDATE_URL) === false
            || ! str_starts_with($advisoryUrl, 'https://')
        ) {
            throw new InvalidArgumentException('The advisory URL must be a valid HTTPS URL.');
        }

        if ($advisoryIds === [] || ! array_is_list($advisoryIds)) {
            throw new InvalidArgumentException('At least one advisory identifier is required.');
        }

        foreach ($advisoryIds as $advisoryId) {
            if (! is_string($advisoryId) || preg_match('~^CVE-\d{4}-\d{4,}$~', $advisoryId) !== 1) {
                throw new InvalidArgumentException('Advisory identifiers must be valid CVE identifiers.');
            }
        }

        $this->fixedReleases = $validatedFixedReleases;
        $this->unsupportedBranches = $validatedUnsupportedBranches;
        $this->advisoryUrl = $advisoryUrl;
        $this->advisoryIds = $advisoryIds;
    }

    public function assess(ImageReference $reference): Assessment
    {
        if (str_contains($reference->image, '@')) {
            return new Assessment(
                $reference,
                AssessmentStatus::Unknown,
                'Pinned digest is not verified.',
                advisoryUrl: $this->advisoryUrl,
                advisoryIds: $this->advisoryIds,
            );
        }

        $tag = $this->extractTag($reference->image);

        if ($tag === null) {
            return new Assessment(
                $reference,
                AssessmentStatus::Unknown,
                'Not an explicit, versioned official PHP image.',
                advisoryUrl: $this->advisoryUrl,
                advisoryIds: $this->advisoryIds,
            );
        }

        $suffixStart = strpos($tag, '-');
        $suffix = $suffixStart === false ? '' : substr($tag, $suffixStart);

        if (preg_match('~(?:^|[-.])(?:alpha|beta|rc|dev)(?:[-.]?\d+)?(?:[-.]|$)~i', $suffix) === 1) {
            return new Assessment(
                $reference,
                AssessmentStatus::Unknown,
                'Prerelease tag; only stable releases are assessed.',
                advisoryUrl: $this->advisoryUrl,
                advisoryIds: $this->advisoryIds,
            );
        }

        if (preg_match('~^(\d+\.\d+\.\d+)(?:[-+].*)?$~', $tag, $matches) !== 1) {
            return new Assessment(
                $reference,
                AssessmentStatus::Unknown,
                'Tag has no full stable PHP version.',
                advisoryUrl: $this->advisoryUrl,
                advisoryIds: $this->advisoryIds,
            );
        }

        $version = $matches[1];

        $branch = implode('.', array_slice(explode('.', $version), 0, 2));

        if (in_array($branch, $this->unsupportedBranches, true)) {
            return new Assessment(
                $reference,
                AssessmentStatus::PotentiallyVulnerable,
                'No fixed release is listed for this branch.',
                $version,
                $this->advisoryUrl,
                $this->advisoryIds,
            );
        }

        $fixedVersion = $this->fixedReleases[$branch] ?? null;

        if ($fixedVersion === null) {
            return new Assessment(
                $reference,
                AssessmentStatus::Unknown,
                'No fixed version is configured for this branch.',
                $version,
                $this->advisoryUrl,
                $this->advisoryIds,
            );
        }

        $status = version_compare($version, $fixedVersion, '<')
            ? AssessmentStatus::PotentiallyVulnerable
            : AssessmentStatus::Ok;

        return new Assessment(
            $reference,
            $status,
            $status === AssessmentStatus::Ok
                ? 'Tag meets upstream fixed version; image not inspected.'
                : 'Tag is below the fixed release.',
            $version,
            $this->advisoryUrl,
            $this->advisoryIds,
        );
    }

    private function extractTag(string $image): ?string
    {
        if (preg_match('~^(?:php|docker\.io/library/php):(?<tag>[^@]+)$~i', $image, $matches) !== 1) {
            return null;
        }

        return $matches['tag'];
    }
}
