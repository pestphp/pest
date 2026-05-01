<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;

/**
 * @internal
 */
final readonly class Symfony implements WatchDefault
{
    public function applicable(): bool
    {
        return class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('symfony/framework-bundle');
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        return [
            'config/*.yaml' => [$testPath],
            'config/*.yml' => [$testPath],
            'config/*.php' => [$testPath],
            'config/*.xml' => [$testPath],
            'config/**/*.yaml' => [$testPath],
            'config/**/*.yml' => [$testPath],
            'config/**/*.php' => [$testPath],
            'config/**/*.xml' => [$testPath],

            'config/routes/*.yaml' => [$testPath],
            'config/routes/*.php' => [$testPath],
            'config/routes/*.xml' => [$testPath],
            'config/routes/**/*.yaml' => [$testPath],

            'src/Kernel.php' => [$testPath],

            'migrations/**/*.php' => [$testPath],
            'src/Migrations/**/*.php' => [$testPath],

            'templates/**/*.html.twig' => [$testPath],
            'templates/**/*.twig' => [$testPath],

            'translations/**/*.yaml' => [$testPath],
            'translations/**/*.yml' => [$testPath],
            'translations/**/*.xlf' => [$testPath],
            'translations/**/*.xliff' => [$testPath],

            'config/doctrine/**/*.xml' => [$testPath],
            'config/doctrine/**/*.yaml' => [$testPath],

            'webpack.config.js' => [$testPath],
            'importmap.php' => [$testPath],
            'assets/**/*.js' => [$testPath],
            'assets/**/*.ts' => [$testPath],
            'assets/**/*.vue' => [$testPath],
            'assets/**/*.css' => [$testPath],
            'assets/**/*.scss' => [$testPath],
        ];
    }
}
