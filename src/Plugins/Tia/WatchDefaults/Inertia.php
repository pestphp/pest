<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;
use Pest\Plugins\Tia\Contracts\WatchDefault;

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
        return [
            'resources/js/** !*.php' => [$testPath],
        ];
    }
}
