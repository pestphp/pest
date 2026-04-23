<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Per-file hashing that ignores changes which can't alter behaviour —
 * comments and whitespace for PHP, `{{-- … --}}` comments and whitespace
 * runs for Blade templates. Every other file type falls back to a plain
 * xxh128 of the raw bytes.
 *
 * Why it matters: TIA's file diff signals drive which tests re-run. A
 * one-line comment tweak on a migration is a behavioural no-op, but the
 * raw-bytes hash still differs, so every test that talks to the DB would
 * currently re-execute. Normalising to the parsed-token / compiled-shape
 * keeps the drift signal honest: edits that can't change runtime
 * behaviour don't invalidate the replay cache.
 *
 * Important: this hash is stored in the graph's last-run tree, so any
 * format change here must be paired with a `Fingerprint::SCHEMA_VERSION`
 * bump — otherwise stale hashes from older graphs would be compared
 * against normalised hashes from the new code and everything would
 * appear changed.
 *
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
}
