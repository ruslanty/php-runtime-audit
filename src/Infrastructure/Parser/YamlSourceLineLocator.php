<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Infrastructure\Parser;

final class YamlSourceLineLocator
{
    /**
     * @param array<string, true> $usedPositions
     */
    public function locate(string $contents, string $image, array &$usedPositions): ?int
    {
        $lines = preg_split('~\R~', $contents);

        if ($lines === false) {
            return null;
        }

        $imageRegex = '~(?:^\s*|[,{]\s*)["\']?image["\']?\s*:\s*(?:(?:!![^\s]+|![^\s]+)\s+)?(?:&[^\s]+\s+)?(?<value>"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^,}\s#]+)~i';
        $anchorRegex = '~&(?<name>[a-zA-Z0-9_-]+)\s+(?<value>"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^,}\s#]+)~';
        $blockScalarIndent = null;
        $anchors = [];

        foreach ($lines as $index => $line) {
            $indent = strlen($line) - strlen(ltrim($line));

            if ($blockScalarIndent !== null) {
                if (trim($line) === ''
                    || $indent > $blockScalarIndent
                ) {
                    continue;
                }

                $blockScalarIndent = null;
            }

            if (preg_match('~^\s*[^#].*:\s*[|>][+-]?\d*(?:[+-])?(?:\s+#.*)?$~', $line) === 1) {
                $blockScalarIndent = $indent;

                continue;
            }

            if (preg_match_all($anchorRegex, $line, $anchorMatches, PREG_SET_ORDER) !== false) {
                foreach ($anchorMatches as $anchorMatch) {
                    $anchors[$anchorMatch['name']] = trim($anchorMatch['value'], "\"'");
                }
            }

            $matchCount = preg_match_all($imageRegex, $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            if ($matchCount === false || $matchCount === 0) {
                continue;
            }

            foreach ($matches as $match) {
                $position = sprintf('%d:%d', $index, $match['value'][1]);

                if (isset($usedPositions[$position])) {
                    continue;
                }

                $sourceValue = trim($match['value'][0], "\"'");

                if (preg_match('~^\*(?<name>[a-zA-Z0-9_-]+)$~', $sourceValue, $aliasMatch) === 1) {
                    $sourceValue = $anchors[$aliasMatch['name']] ?? $sourceValue;
                }

                if ($sourceValue !== $image
                    && preg_match('~^[|>][+-]?\d*(?:[+-])?$~', $sourceValue) !== 1
                ) {
                    continue;
                }

                $usedPositions[$position] = true;

                return $index + 1;
            }
        }

        return null;
    }
}
