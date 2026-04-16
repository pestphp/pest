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
            // Inertia page components (React / Vue / Svelte).
            'resources/js/Pages/**/*.vue' => [$testPath, $browserDir],
            'resources/js/Pages/**/*.tsx' => [$testPath, $browserDir],
            'resources/js/Pages/**/*.jsx' => [$testPath, $browserDir],
            'resources/js/Pages/**/*.svelte' => [$testPath, $browserDir],

            // Shared layouts / components consumed by pages.
            'resources/js/Layouts/**/*.vue' => [$browserDir],
            'resources/js/Layouts/**/*.tsx' => [$browserDir],
            'resources/js/Components/**/*.vue' => [$browserDir],
            'resources/js/Components/**/*.tsx' => [$browserDir],

            // SSR entry point.
            'resources/js/ssr.js' => [$browserDir],
            'resources/js/ssr.ts' => [$browserDir],
            'resources/js/app.js' => [$browserDir],
            'resources/js/app.ts' => [$browserDir],
        ];
    }
}
