<?php

namespace App\Support;

use RuntimeException;

final class LlmJsonExtractor
{
    /**
     * @throws RuntimeException
     */
    public static function extract(string $output, bool $assoc = false): array
    {
        $output = trim($output);

        // 1. Direct decode
        if ($json = self::tryDecode($output, $assoc)) {
            return $json;
        }

        // 2. Strip markdown fences
        $stripped = self::stripMarkdown($output);
        if ($json = self::tryDecode($stripped, $assoc)) {
            return $json;
        }

        // 3. strpos / strrpos fallback
        if ($candidate = self::extractByBounds($stripped)) {
            if ($json = self::tryDecode($candidate, $assoc)) {
                return $json;
            }
        }

        // 4. Balanced brace parser (last resort)
        if ($candidate = self::extractBalancedJson($stripped)) {
            if ($json = self::tryDecode($candidate, $assoc)) {
                return $json;
            }
        }

        throw new RuntimeException(
            'Unable to extract valid JSON from LLM output'
        );
    }

    private static function tryDecode(string $value, bool $assoc): ?array
    {
        $decoded = json_decode($value, $assoc);

        return json_last_error() === JSON_ERROR_NONE
            ? $decoded
            : null;
    }

    private static function stripMarkdown(string $value): string
    {
        return trim(
            preg_replace('/```(?:json)?\s*|\s*```/i', '', $value)
        );
    }

    private static function extractByBounds(string $value): ?string
    {
        $start = strpos($value, '{');
        $end   = strrpos($value, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($value, $start, $end - $start + 1);
    }

    /**
     * Extract first balanced JSON object from text
     */
    private static function extractBalancedJson(string $text): ?string
    {
        $depth = 0;
        $start = null;
        $inString = false;
        $escape = false;
        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];

            if ($ch === '"' && !$escape) {
                $inString = !$inString;
            }

            if ($inString) {
                $escape = ($ch === '\\' && !$escape);
                continue;
            }

            if ($ch === '{') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    return substr($text, $start, $i - $start + 1);
                }
            }

            $escape = false;
        }

        return null;
    }
}
