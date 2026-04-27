<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;

/**
 * Watch patterns for Inertia.js projects (Laravel or otherwise).
 *
 * Inertia bridges PHP controllers with JS/TS page components. A change to
 * a React / Vue / Svelte page can break assertions in browser tests or
 * Inertia-specific feature tests.
 *
 * @internal
 */
final readonly class Inertia implements WatchDefault
{
    public function applicable(): bool
    {
        return class_exists(InstalledVersions::class)
            && (InstalledVersions::isInstalled('inertiajs/inertia-laravel')
                || InstalledVersions::isInstalled('rompetomp/inertia-bundle'));
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        $browserDir = is_dir($projectRoot.DIRECTORY_SEPARATOR.$testPath.'/Browser')
            ? $testPath.'/Browser'
            : $testPath;

        // Inertia page components (React / Vue / Svelte). Scoped to
        // `$browserDir` only — a Vue/React edit cannot change the
        // output of a server-side Inertia test (those assert on the
        // component *name* returned by `Inertia::render()`, not its
        // client-side implementation). Broad invalidation is only
        // meaningful for tests that actually render the DOM. Precise
        // per-component edges come from `InertiaEdges` at record
        // time and replace this fallback when available.
        //
        // Both `Pages/` (classic Inertia-Vue) and `pages/` (Laravel
        // React starter kit, and other lowercase-by-default setups)
        // are emitted — paths from git are case-sensitive on Linux,
        // so a single casing would silently miss the other convention.
        $patterns = [];

        foreach (['Pages', 'pages'] as $pages) {
            foreach (['vue', 'tsx', 'jsx', 'svelte', 'ts', 'js'] as $ext) {
                $patterns["resources/js/{$pages}/**/*.{$ext}"] = [$browserDir];
            }
        }

        foreach (['Layouts', 'layouts', 'Components', 'components'] as $shared) {
            foreach (['vue', 'tsx', 'ts', 'js'] as $ext) {
                $patterns["resources/js/{$shared}/**/*.{$ext}"] = [$browserDir];
            }
        }

        // SSR entry point.
        $patterns['resources/js/ssr.js'] = [$browserDir];
        $patterns['resources/js/ssr.ts'] = [$browserDir];
        $patterns['resources/js/app.js'] = [$browserDir];
        $patterns['resources/js/app.ts'] = [$browserDir];

        return $patterns;
    }
}
