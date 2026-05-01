<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class ContentHash
{
    /**
     * xxh128 hex of the file's "behavioural" shape, or `false` when the
     * file can't be read. Callers should treat `false` the same way they
     * treated a failed `hash_file()` previously.
     */
    public static function of(string $absolute): string|false
    {
        $raw = @file_get_contents($absolute);

        if ($raw === false) {
            return false;
        }

        return self::ofContent($absolute, $raw);
    }

    /**
     * Same as `of()` but accepts the file contents in memory. Used when
     * we already have the bytes (e.g. from `git show <sha>:<path>`) and
     * want to avoid a disk round-trip.
     */
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

    /**
     * Tokenise the content and hash the concatenated values of every
     * token except whitespace / comment / docblock. `token_get_all()`
     * is built-in, fast, and enough to collapse any formatting-only
     * edit. If tokenisation fails (rare syntax error), fall back to
     * the raw hash so the caller still gets a deterministic signal.
     */
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

    /**
     * Blade templates aren't PHP syntactically, so `token_get_all()`
     * doesn't help. Strip `{{-- … --}}` comments (the only Blade-native
     * comment form) and collapse whitespace runs. Output differences
     * that would survive the Blade compiler (markup reordering, new
     * directives, changed interpolation) still flip the hash; pure
     * reformatting does not.
     */
    private static function hashBladeContent(string $raw): string
    {
        $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $raw) ?? $raw;
        $stripped = preg_replace('/\s+/', ' ', $stripped) ?? $stripped;

        return hash('xxh128', trim($stripped));
    }

    /**
     * Conservative JS/TS/Vue/Svelte normaliser. Strips `//` line
     * comments and `/* … *\/` block comments that appear on their own
     * lines (including leading indentation), then collapses
     * whitespace. Deliberately leaves trailing comments after code
     * alone — a string literal like `'http://foo'` would be unsafe to
     * split on `//` without a full lexer. The direction of error is
     * over-detection (we may not strip a trailing comment that's
     * purely cosmetic), never under-detection. Blank lines and
     * indentation changes are erased regardless.
     */
    private static function hashJsContent(string $raw): string
    {
        $stripped = preg_replace('/^\s*\/\/[^\n]*$/m', '', $raw) ?? $raw;
        $stripped = preg_replace('/^\s*\/\*.*?\*\/\s*$/sm', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s+/', ' ', $stripped) ?? $stripped;

        return hash('xxh128', trim($stripped));
    }
}
