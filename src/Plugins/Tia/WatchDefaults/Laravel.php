<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;

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
            'config/*.php' => [$testPath],
            'config/**/*.php' => [$testPath],

            'routes/*.php' => [$testPath],
            'routes/**/*.php' => [$testPath],

            'bootstrap/app.php' => [$testPath],
            'bootstrap/providers.php' => [$testPath],

            'database/migrations/**/*.php' => [$testPath],

            'database/seeders/**/*.php' => [$testPath],

            'database/factories/**/*.php' => [$testPath],

            'storage/fixtures/**/*' => [$testPath],

            'app/**/*.tpl' => [$testPath],
            'app/**/*.stub' => [$testPath],
            'app/**/*.json' => [$testPath],
            'app/**/*.yaml' => [$testPath],
            'app/**/*.yml' => [$testPath],
            'app/**/*.txt' => [$testPath],

            'resources/views/**/*.blade.php' => [$testPath],
            'resources/views/**/*.css' => [$testPath],
            'resources/views/email/**/*.blade.php' => [$testPath],
            'resources/views/emails/**/*.blade.php' => [$testPath],

            'lang/**/*.php' => [$testPath],
            'lang/**/*.json' => [$testPath],
            'resources/lang/**/*.php' => [$testPath],
            'resources/lang/**/*.json' => [$testPath],

            'vite.config.js' => [$testPath],
            'vite.config.ts' => [$testPath],
            'webpack.mix.js' => [$testPath],
            'tailwind.config.js' => [$testPath],
            'tailwind.config.ts' => [$testPath],
            'postcss.config.js' => [$testPath],
        ];
    }
}
