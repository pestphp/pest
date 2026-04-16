<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;

/**
 * Watch patterns for projects using Livewire.
 *
 * Livewire components pair a PHP class with a Blade view. A view change can
 * break rendering or assertions in feature / browser tests even though the
 * PHP side is untouched.
 *
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

            // Livewire JS interop / Alpine plugins.
            'resources/js/**/*.js' => [$testPath],
            'resources/js/**/*.ts' => [$testPath],
        ];
    }
}
