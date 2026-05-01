<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;

/**
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
        $browserTargets = Browser::detectBrowserTestTargets($projectRoot, $testPath);

        $patterns = [];

        foreach (['Pages', 'pages'] as $pages) {
            foreach (['vue', 'tsx', 'jsx', 'svelte', 'ts', 'js'] as $ext) {
                $patterns["resources/js/{$pages}/**/*.{$ext}"] = $browserTargets;
            }
        }

        foreach (['Layouts', 'layouts', 'Components', 'components'] as $shared) {
            foreach (['vue', 'tsx', 'ts', 'js'] as $ext) {
                $patterns["resources/js/{$shared}/**/*.{$ext}"] = $browserTargets;
            }
        }

        $patterns['resources/js/ssr.js'] = $browserTargets;
        $patterns['resources/js/ssr.ts'] = $browserTargets;
        $patterns['resources/js/app.js'] = $browserTargets;
        $patterns['resources/js/app.ts'] = $browserTargets;

        return $patterns;
    }
}
