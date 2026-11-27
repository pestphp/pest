<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Pest\Plugins\Tia\Contracts\WatchDefault;
use Pest\Support\InstalledPackages;

/**
 * @internal
 */
final readonly class Inertia implements WatchDefault
{
    public function applicable(): bool
    {
        return InstalledPackages::has('inertiajs/inertia-laravel')
            || InstalledPackages::has('rompetomp/inertia-bundle');
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        return [
            'resources/js/** !*.php' => [$testPath],
        ];
    }
}
