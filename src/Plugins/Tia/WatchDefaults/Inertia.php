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

        return [
            // Inertia page components (React / Vue / Svelte). Scoped to
            // `$browserDir` only — a Vue/React edit cannot change the
            // output of a server-side Inertia test (those assert on the
            // component *name* returned by `Inertia::render()`, not its
            // client-side implementation). Broad invalidation is only
            // meaningful for tests that actually render the DOM. Precise
            // per-component edges come from `InertiaEdges` at record
            // time and replace this fallback when available.
            'resources/js/Pages/**/*.vue' => [$browserDir],
            'resources/js/Pages/**/*.tsx' => [$browserDir],
            'resources/js/Pages/**/*.jsx' => [$browserDir],
            'resources/js/Pages/**/*.svelte' => [$browserDir],
            'resources/js/Pages/**/*.ts' => [$browserDir],
            'resources/js/Pages/**/*.js' => [$browserDir],

            // Shared layouts / components consumed by pages.
            'resources/js/Layouts/**/*.vue' => [$browserDir],
            'resources/js/Layouts/**/*.tsx' => [$browserDir],
            'resources/js/Layouts/**/*.ts' => [$browserDir],
            'resources/js/Layouts/**/*.js' => [$browserDir],
            'resources/js/Components/**/*.vue' => [$browserDir],
            'resources/js/Components/**/*.tsx' => [$browserDir],
            'resources/js/Components/**/*.ts' => [$browserDir],
            'resources/js/Components/**/*.js' => [$browserDir],

            // SSR entry point.
            'resources/js/ssr.js' => [$browserDir],
            'resources/js/ssr.ts' => [$browserDir],
            'resources/js/app.js' => [$browserDir],
            'resources/js/app.ts' => [$browserDir],
        ];
    }
}
