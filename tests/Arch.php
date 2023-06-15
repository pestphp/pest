<?php

use Pest\Expectation;

test('globals')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed()
    ->ignoring(Expectation::class);

test('dependencies')
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

test('contracts')
    ->expect('Pest\Contracts')
    ->toOnlyUse([
        'NunoMaduro\Collision\Contracts',
        'Pest\Factories\TestCaseMethodFactory',
        'Symfony\Component\Console',
    ])->toBeInterfaces();
