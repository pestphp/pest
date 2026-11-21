<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Pest\Plugins\Tia\Contracts\WatchDefault;
use Pest\Support\InstalledPackages;

/**
 * @internal
 */
final readonly class Laravel implements WatchDefault
{
    public function applicable(): bool
    {
        return InstalledPackages::has('laravel/framework');
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        return [
            'database/migrations/**/*.php' => [$testPath],

            'config/**/*.php' => [$testPath],
            'bootstrap/*.php' => [$testPath],

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
