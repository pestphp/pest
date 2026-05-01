<?php

use Pest\Expectation;
use Pest\Plugins\Tia\BaselineSync;

arch()->preset()->php()->ignoring([
    Expectation::class,
    'debug_backtrace',
    'var_export',
    'xdebug_info',
    'xdebug_start_code_coverage',
    'xdebug_stop_code_coverage',
    'xdebug_get_code_coverage',
]);

arch()->preset()->strict()->ignoring([
    BaselineSync::class,
    'usleep',
]);

arch()->preset()->security()->ignoring([
    'eval',
    'str_shuffle',
    'exec',
    'md5',
    'unserialize',
    'uniqid',
    'extract',
    'assert',
]);

arch('globals')
    ->expect(['dd', 'dump', 'ray', 'die', 'var_dump', 'sleep'])
    ->not->toBeUsed()
    ->ignoring(Expectation::class);

arch('contracts')
    ->expect('Pest\Contracts')
    ->toOnlyUse([
        'NunoMaduro\Collision\Contracts',
        'Pest\Factories\TestCaseMethodFactory',
        'Symfony\Component\Console',
        'Pest\Arch\Contracts',
        'Pest\PendingCalls',
    ])->toBeInterfaces();
