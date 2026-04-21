<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;

/**
 * Watch patterns for Symfony projects.
 *
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
        // Symfony boots the kernel in setUp() (before the coverage window).
        // PHP config, routes, kernel, and migrations are loaded during boot
        // and invisible to the coverage driver. Same reasoning as Laravel.

        return [
            // Config — YAML, XML, and PHP. All loaded during kernel boot.
            'config/*.yaml' => [$testPath],
            'config/*.yml' => [$testPath],
            'config/*.php' => [$testPath],
            'config/*.xml' => [$testPath],
            'config/**/*.yaml' => [$testPath],
            'config/**/*.yml' => [$testPath],
            'config/**/*.php' => [$testPath],
            'config/**/*.xml' => [$testPath],

            // Routes — loaded during boot.
            'config/routes/*.yaml' => [$testPath],
            'config/routes/*.php' => [$testPath],
            'config/routes/*.xml' => [$testPath],
            'config/routes/**/*.yaml' => [$testPath],

            // Kernel / bootstrap — loaded during boot.
            'src/Kernel.php' => [$testPath],

            // Migrations — run during setUp (before coverage window).
            // DoctrineMigrationsBundle's default is `migrations/` at the
            // project root; many Symfony projects relocate to
            // `src/Migrations/` — both covered.
            'migrations/**/*.php' => [$testPath],
            'src/Migrations/**/*.php' => [$testPath],

            // Twig templates — compiled, source not PHP-executed.
            'templates/**/*.html.twig' => [$testPath],
            'templates/**/*.twig' => [$testPath],

            // Translations (YAML / XLF / XLIFF).
            'translations/**/*.yaml' => [$testPath],
            'translations/**/*.yml' => [$testPath],
            'translations/**/*.xlf' => [$testPath],
            'translations/**/*.xliff' => [$testPath],

            // Doctrine XML/YAML mappings.
            'config/doctrine/**/*.xml' => [$testPath],
            'config/doctrine/**/*.yaml' => [$testPath],

            // Webpack Encore / asset-mapper config + frontend sources.
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
