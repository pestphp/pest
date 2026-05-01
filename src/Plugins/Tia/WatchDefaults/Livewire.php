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
            'resources/views/livewire/**/*.blade.php' => [$testPath],
            'resources/views/components/**/*.blade.php' => [$testPath],
            'resources/views/pages/**/*.blade.php' => [$testPath],

            'resources/js/**/*.js' => [$testPath],
            'resources/js/**/*.ts' => [$testPath],
        ];
    }
}
