<?php

test('depends on other file - first same class', function () use (&$runCounter) {
    expect(true)->toBeTrue();

    return 'first same class';
});

test('depends on other file - second same class', function () use (&$runCounter) {
    expect(true)->toBeTrue();

    return 'second same class';
});

test('depends on other file - test invoke signature 1', function () {
    expect(func_get_args())->toBe(['first', 'second']);
})->depends(
    ['\Tests\Features\Depends', 'first'],
    ['\Tests\Features\Depends', 'second'],
);

test('depends on other file - test invoke signature 2', function ($first, $second) {
    expect($first)->toBe('first');
    expect($second)->toBe('second');
})->depends(
    ['\Tests\Features\Depends', 'first'],
    ['\Tests\Features\Depends', 'second'],
);

test('depends on other file - test mixed parameters 1', function () {
    expect(func_get_args())->toBe(['first', 'second same class']);
})->depends(
    ['\Tests\Features\Depends', 'first'],
    'depends on other file - second same class',
);

test('depends on other file - test mixed parameters 2', function ($first, $second) {
    expect($first)->toBe('first same class');
    expect($second)->toBe('second');
})->depends(
    'depends on other file - first same class',
    ['\Tests\Features\Depends', 'second'],
);
