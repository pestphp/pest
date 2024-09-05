<?php

use Pest\Expectation;

arch()->preset()->php()->ignoring([
    Expectation::class,
    'debug_backtrace',
    'var_export',
    'xdebug_info',
]);

arch()->preset()->strict()->ignoring([
    'usleep',
]);

arch()->preset()->security()->ignoring([
    'eval',
    'str_shuffle',
    'exec',
    'unserialize',
    'extract',
    'assert',
]);

arch('globals')
    ->expect(['dd', 'dump', 'ray', 'die', 'var_dump', 'sleep'])
    ->not->toBeUsed()
    ->ignoring(Expectation::class);

arch('dependencies')
    ->expect('Pest')
    ->toOnlyUse([
        'dd',
        'dump',
        'expect',
        'uses',
        'Termwind',
        'ParaTest',
        'Pest\Arch',
        'Pest\Mutate\Contracts\Configuration',
        'Pest\Mutate\Decorators\TestCallDecorator',
        'Pest\Mutate\Repositories\ConfigurationRepository',
        'Pest\Plugin',
        'NunoMaduro\Collision',
        'Whoops',
        'Symfony\Component\Console',
        'Symfony\Component\Process',
    ])->ignoring(['Composer', 'PHPUnit', 'SebastianBergmann']);

arch('contracts')
    ->expect('Pest\Contracts')
    ->toOnlyUse([
        'NunoMaduro\Collision\Contracts',
        'Pest\Factories\TestCaseMethodFactory',
        'Symfony\Component\Console',
        'Pest\Arch\Contracts',
        'Pest\PendingCalls',
    ])->toBeInterfaces();
