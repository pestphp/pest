<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Pest\Plugins\Tia\Contracts\WatchDefault;
use Pest\Support\InstalledPackages;

/**
 * @internal
 */
final readonly class Symfony implements WatchDefault
{
    public function applicable(): bool
    {
        return InstalledPackages::has('symfony/framework-bundle');
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        return [
            'config/** !*.php' => [$testPath],
            'config/routes/** !*.php' => [$testPath],

            'migrations/**/*.php' => [$testPath],
            'src/Migrations/**/*.php' => [$testPath],

            'templates/** !*.php' => [$testPath],

            'translations/** !*.php' => [$testPath],

            'config/doctrine/**/*.xml' => [$testPath],
            'config/doctrine/**/*.yaml' => [$testPath],

            'webpack.config.js' => [$testPath],
            'importmap.php' => [$testPath],
            'assets/** !*.php' => [$testPath],
        ];
    }
}
