<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;

/**
 * @internal
 */
final readonly class Livewire implements WatchDefault
{
    public function applicable(): bool
    {
        return class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('livewire/livewire');
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        return [
            // Livewire views live alongside Blade views or in a dedicated dir.
            'resources/views/livewire/**/*.blade.php' => [$testPath],
            'resources/views/components/**/*.blade.php' => [$testPath],
            // Volt's second default mount — single-file components used as
            // full-page routes. Missing this means editing a Volt page
            // doesn't re-run its tests.
            'resources/views/pages/**/*.blade.php' => [$testPath],

            // Livewire JS interop / Alpine plugins.
            'resources/js/**/*.js' => [$testPath],
            'resources/js/**/*.ts' => [$testPath],
        ];
    }
}
