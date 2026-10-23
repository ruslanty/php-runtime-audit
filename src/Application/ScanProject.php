<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Application;

use PhpRuntimeAudit\Domain\Assessment;
use PhpRuntimeAudit\Domain\UpstreamVersionPolicy;

final readonly class ScanProject
{
    /**
     * @param list<ManifestParser> $parsers
     */
    public function __construct(
        private ManifestDiscovery $discovery,
        private array $parsers,
        private UpstreamVersionPolicy $policy,
    ) {}

    /**
     * @return list<Assessment>
     */
    public function scan(string $root): array
    {
        $assessments = [];

        foreach ($this->discovery->find($root) as $file) {
            foreach ($this->parsers as $parser) {
                if (! $parser->supports($file->path)) {
                    continue;
                }

                foreach ($parser->parse($file) as $reference) {
                    $assessments[] = $this->policy->assess($reference);
                }
            }
        }

        return $assessments;
    }
}
