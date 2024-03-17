<?php

use Pest\Expectation;

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
    ])->toBeInterfaces();
