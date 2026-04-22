<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Resolves TIA's on-disk state directory.
 *
 * Default location: `$HOME/.pest/tia/<project-key>/`. Keeping state in the
 * user's home directory (rather than under `vendor/pestphp/pest/`) means:
 *
 *   - `composer install` / path-repo reinstalls don't wipe the graph.
 *   - The state lives outside the project tree, so there is nothing for
 *     users to gitignore or accidentally commit.
 *   - Multiple worktrees of the same repo share one cache naturally.
 *
 * The project key is derived from the git origin URL when available — a
 * CI workflow running on `github.com/org/repo` and a developer's clone
 * of the same remote both compute the *same* key, which is what lets the
 * CI-uploaded baseline line up with the dev-side reader. When the project
 * is not in git, the key falls back to a hash of the absolute path so
 * unrelated projects on the same machine stay isolated.
 *
 * When no home directory is resolvable (`HOME` / `USERPROFILE` both
 * unset — the tests-tia sandboxes strip these deliberately, and some
 * locked-down CI environments do the same), state falls back to
 * `<projectRoot>/.pest/tia/`. That path is project-local but still
 * survives composer installs, so the degradation is graceful.
 *
 * @internal
 */
final class Storage
{
    /**
     * Directory where TIA's State blobs live for `$projectRoot`.
     */
    public static function tempDir(string $projectRoot): string
    {
        $home = self::homeDir();

        if ($home === null) {
            return $projectRoot
                .DIRECTORY_SEPARATOR.'.pest'
                .DIRECTORY_SEPARATOR.'tia';
        }

        return $home
            .DIRECTORY_SEPARATOR.'.pest'
            .DIRECTORY_SEPARATOR.'tia'
            .DIRECTORY_SEPARATOR.self::projectKey($projectRoot);
    }

    /**
     * OS-neutral home directory — `HOME` on Unix, `USERPROFILE` on
     * Windows. Returns null if neither resolves to an existing
     * directory, in which case callers fall back to project-local state.
     */
    private static function homeDir(): ?string
    {
        foreach (['HOME', 'USERPROFILE'] as $key) {
            $value = getenv($key);

            if (is_string($value) && $value !== '' && is_dir($value)) {
                return rtrim($value, '/\\');
            }
        }

        return null;
    }

    /**
     * Folder name for `$projectRoot` under `~/.pest/tia/`.
     *
     * Strategy — each step rules out a class of collision:
     *
     *   1. If the project has a git origin URL, use a **normalised** form
     *      (`host/org/repo`, lowercased, no `.git` suffix) as the input.
     *      `git@github.com:foo/bar.git`, `ssh://git@github.com/foo/bar`
     *      and `https://github.com/foo/bar` all collapse to
     *      `github.com/foo/bar` — three developers cloning the same repo
     *      by different transports share one cache, which is what we want.
     *   2. Otherwise, use the canonicalised absolute path (`realpath`).
     *      Two unrelated `app/` checkouts under different parent folders
     *      have different realpaths → different hashes → isolated.
     *   3. Hash the chosen input with sha256 and keep the first 16 hex
     *      chars — 64 bits of entropy makes accidental collision
     *      astronomically unlikely even across thousands of projects.
     *   4. Prefix with a slug of the project basename so `ls ~/.pest/tia/`
     *      is readable; the slug is cosmetic only, all isolation comes
     *      from the hash.
     *
     * Result: `myapp-a1b2c3d4e5f67890`.
     */
    private static function projectKey(string $projectRoot): string
    {
        $origin = self::originIdentity($projectRoot);

        $realpath = @realpath($projectRoot);
        $input = $origin ?? ($realpath === false ? $projectRoot : $realpath);

        $hash = substr(hash('sha256', $input), 0, 16);
        $slug = self::slug(basename($projectRoot));

        return $slug === '' ? $hash : $slug.'-'.$hash;
    }

    /**
     * Canonical git origin identity for `$projectRoot`, or null when
     * no origin URL can be parsed. The returned form is
     * `host/org/repo` (lowercased, `.git` stripped) so SSH / HTTPS / git
     * protocol clones of the same remote produce the same value.
     */
    private static function originIdentity(string $projectRoot): ?string
    {
        $url = self::rawOriginUrl($projectRoot);

        if ($url === null) {
            return null;
        }

        // git@host:org/repo(.git)
        if (preg_match('#^[\w.-]+@([\w.-]+):([\w./-]+?)(?:\.git)?/?$#', $url, $m) === 1) {
            return strtolower($m[1].'/'.$m[2]);
        }

        // scheme://[user@]host[:port]/org/repo(.git)  — https, ssh, git, file
        if (preg_match('#^[a-z]+://(?:[^@/]+@)?([^/:]+)(?::\d+)?/([\w./-]+?)(?:\.git)?/?$#i', $url, $m) === 1) {
            return strtolower($m[1].'/'.$m[2]);
        }

        // Unrecognised form — hash the raw URL so different inputs still
        // diverge, but lowercased so the only variance is intentional.
        return strtolower($url);
    }

    private static function rawOriginUrl(string $projectRoot): ?string
    {
        $config = $projectRoot.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config';

        if (! is_file($config)) {
            return null;
        }

        $raw = @file_get_contents($config);

        if ($raw === false) {
            return null;
        }

        if (preg_match('/\[remote "origin"\][^\[]*?url\s*=\s*(\S+)/s', $raw, $match) === 1) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Filesystem-safe kebab of `$name`. Cosmetic only — used as a
     * human-readable prefix on the hash so `~/.pest/tia/` lists
     * recognisable folders.
     */
    private static function slug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
