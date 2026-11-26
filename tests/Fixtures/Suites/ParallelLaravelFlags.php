<?php

declare(strict_types=1);

namespace Illuminate\Testing;

use Closure;
use Composer\InstalledVersions;
use Pest\Plugins\Parallel\Handlers\Laravel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

final class ParallelRunner
{
    public static function resolveRunnerUsing(Closure $resolver): void {}
}

$installed = InstalledVersions::getAllRawData()[0];
$installed['versions']['laravel/framework'] = ['dev_requirement' => false];
InstalledVersions::reload($installed);

(new Laravel)->handleArguments($argv);

$flags = array_flip([
    'LARAVEL_PARALLEL_TESTING',
    'LARAVEL_PARALLEL_TESTING_RECREATE_DATABASES',
    'LARAVEL_PARALLEL_TESTING_DROP_DATABASES',
]);

echo json_encode([
    'server' => array_keys(array_filter(array_intersect_key($_SERVER, $flags))),
    'env' => array_keys(array_filter(array_intersect_key($_ENV, $flags))),
], JSON_THROW_ON_ERROR);
