<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Infrastructure\Parser;

final class PhpRuntimeImageMatcher
{
    public function matches(string $image): bool
    {
        return preg_match('~(?:^|/)(?:php(?:\d+|[-_.][^/:@]*)?)(?=[:/@]|$)~i', $image) === 1;
    }
}
