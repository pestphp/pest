<?php

declare(strict_types=1);

namespace Pest\Support;

use Illuminate\Support\Env;
use Laravel\Tinker\ClassAliasAutoloader;
use Pest\TestSuite;
use Psy\Configuration;
use Psy\Shell as PsyShell;
use Psy\VersionUpdater\Checker;

/**
 * @internal
 */
final class Shell
{
    public static function open(): void
    {
        $config = new Configuration;

        $config->setUpdateCheck(Checker::NEVER);

        $config->getPresenter()->addCasters(self::casters());

        $shell = new PsyShell($config);

        $loader = self::tinkered($shell);

        try {
            $shell->run();
        } finally {
            $loader?->unregister(); // @phpstan-ignore-line
        }
    }

    /**
     * @return array<string, callable>
     */
    private static function casters(): array
    {
        $casters = [
            'Illuminate\Support\Collection' => 'Laravel\Tinker\TinkerCaster::castCollection',
            'Illuminate\Support\HtmlString' => 'Laravel\Tinker\TinkerCaster::castHtmlString',
            'Illuminate\Support\Stringable' => 'Laravel\Tinker\TinkerCaster::castStringable',
        ];

        if (class_exists('Illuminate\Database\Eloquent\Model')) {
            $casters['Illuminate\Database\Eloquent\Model'] = 'Laravel\Tinker\TinkerCaster::castModel';
        }

        if (class_exists('Illuminate\Process\ProcessResult')) {
            $casters['Illuminate\Process\ProcessResult'] = 'Laravel\Tinker\TinkerCaster::castProcessResult';
        }

        if (class_exists('Illuminate\Foundation\Application')) {
            $casters['Illuminate\Foundation\Application'] = 'Laravel\Tinker\TinkerCaster::castApplication';
        }

        if (function_exists('app') === false) {
            return $casters; // @phpstan-ignore-line
        }

        $config = app()->make('config');

        return array_merge($casters, (array) $config->get('tinker.casters', []));
    }

    private static function tinkered(PsyShell $shell): ?object
    {
        if (function_exists('app') === false
            || ! class_exists(Env::class)
            || ! class_exists(ClassAliasAutoloader::class)
        ) {
            return null;
        }

        $path = Env::get('COMPOSER_VENDOR_DIR', app()->basePath().DIRECTORY_SEPARATOR.'vendor');

        $path .= '/composer/autoload_classmap.php';

        if (! file_exists($path)) {
            $path = TestSuite::getInstance()->rootPath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'autoload_classmap.php';
        }

        $config = app()->make('config');

        return ClassAliasAutoloader::register(
            $shell, $path, $config->get('tinker.alias', []), $config->get('tinker.dont_alias', [])
        );
    }
}
