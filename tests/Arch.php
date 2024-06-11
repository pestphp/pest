<?php

use Pest\Expectation;

uses()->group('arch');

arch()->preset()->base()->ignoring([
    Expectation::class,
    'debug_backtrace',
    'var_export',
]);

arch()->preset()->strict()->ignoring([
    'usleep',
]);

arch()->preset()->security()->ignoring([
    'str_shuffle',
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
