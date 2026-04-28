<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;

/**
 * Watch patterns for Laravel projects.
 *
 * Laravel boots the entire application inside `setUp()` (before PHPUnit's
 * `Prepared` event where TIA's coverage window opens). That means PHP files
 * loaded during boot — config, routes, service providers, migrations — are
 * invisible to the coverage driver. Watch patterns are the only way to
 * track them.
 *
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
            // Config — loaded during app boot (setUp), invisible to coverage.
            // Affects both Feature and Unit: Pest.php commonly binds fakes
            // and seeds DB based on config values.
            'config/*.php' => [$testPath],
            'config/**/*.php' => [$testPath],

            // Routes — loaded during boot. HTTP/Feature tests depend on them.
            'routes/*.php' => [$testPath],
            'routes/**/*.php' => [$testPath],

            // Service providers / bootstrap — loaded during boot, affect
            // bindings, middleware, event listeners, scheduled tasks.
            'bootstrap/app.php' => [$testPath],
            'bootstrap/providers.php' => [$testPath],

            // Migrations — run via RefreshDatabase/FastRefreshDatabase in
            // setUp. Schema changes can break any test that touches DB.
            'database/migrations/**/*.php' => [$testPath],

            // Seeders — often run globally via Pest.php beforeEach.
            'database/seeders/**/*.php' => [$testPath],

            // Factories — loaded lazily but still PHP that coverage may miss
            // if the factory file was already autoloaded before Prepared.
            'database/factories/**/*.php' => [$testPath],

            // Blade templates — compiled to cache, source file not executed.
            'resources/views/**/*.blade.php' => [$testPath],
            // Email templates are nested under views/email or views/emails
            // by convention and power mailable tests that render markup.
            'resources/views/email/**/*.blade.php' => [$testPath],
            'resources/views/emails/**/*.blade.php' => [$testPath],

            // Translations — JSON translations read via file_get_contents,
            // PHP translations loaded via include (but during boot).
            'lang/**/*.php' => [$testPath],
            'lang/**/*.json' => [$testPath],
            'resources/lang/**/*.php' => [$testPath],
            'resources/lang/**/*.json' => [$testPath],

            // Build tool config — affects compiled assets consumed by
            // browser and Inertia tests.
            'vite.config.js' => [$testPath],
            'vite.config.ts' => [$testPath],
            'webpack.mix.js' => [$testPath],
            'tailwind.config.js' => [$testPath],
            'tailwind.config.ts' => [$testPath],
            'postcss.config.js' => [$testPath],
        ];
    }
}
