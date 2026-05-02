<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;
use Pest\Plugins\Tia\Contracts\WatchDefault;

/**
 * @internal
 */
final readonly class Laravel implements WatchDefault
{
    public function applicable(): bool
    {
        return class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('laravel/framework');
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        return [
            'database/migrations/**/*.php' => [$testPath],

            'storage/fixtures/**/*' => [$testPath],

            'app/** !*.php' => [$testPath],

            'resources/views/**' => [$testPath],

            'lang/**' => [$testPath],
            'resources/lang/**' => [$testPath],

            'vite.config.* !*.php' => [$testPath],
            'webpack.mix.* !*.php' => [$testPath],
            'tailwind.config.* !*.php' => [$testPath],
            'postcss.config.* !*.php' => [$testPath],
        ];
    }
}
