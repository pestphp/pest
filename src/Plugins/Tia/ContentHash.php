<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class ContentHash
{
    public static function of(string $absolute): string|false
    {
        $raw = @file_get_contents($absolute);

        if ($raw === false) {
            return false;
        }

        return self::ofContent($absolute, $raw);
    }

    public static function ofContent(string $path, string $raw): string
    {
        $lower = strtolower($path);

        if (str_ends_with($lower, '.blade.php')) {
            return self::hashBladeContent($raw);
        }

        if (str_ends_with($lower, '.php')) {
            return self::hashPhpContent($raw);
        }

        foreach (['.vue', '.tsx', '.jsx', '.svelte', '.ts', '.js', '.mjs', '.cjs', '.mts'] as $extension) {
            if (str_ends_with($lower, $extension)) {
                return self::hashJsContent($raw);
            }
        }

        return hash('xxh128', $raw);
    }

    private static function hashPhpContent(string $raw): string
    {
        $tokens = @token_get_all($raw);

        if ($tokens === []) {
            return hash('xxh128', $raw);
        }

        $normalised = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_WHITESPACE) {
                    continue;
                }
                if ($token[0] === T_COMMENT) {
                    continue;
                }
                if ($token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $normalised .= $token[1];
            } else {
                $normalised .= $token;
            }
        }

        return hash('xxh128', $normalised);
    }

    private static function hashBladeContent(string $raw): string
    {
        $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $raw) ?? $raw;
        $stripped = preg_replace('/\s+/', ' ', $stripped) ?? $stripped;

        return hash('xxh128', trim($stripped));
    }

    private static function hashJsContent(string $raw): string
    {
        $stripped = preg_replace('/^\s*\/\/[^\n]*$/m', '', $raw) ?? $raw;
        $stripped = preg_replace('/^\s*\/\*.*?\*\/\s*$/sm', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s+/', ' ', $stripped) ?? $stripped;

        return hash('xxh128', trim($stripped));
    }
}
