<?php

$runCounter = 0;

test('first', function () use (&$runCounter) {
    expect(true)->toBeTrue();
    $runCounter++;

    return 'first';
});

test('second', function () use (&$runCounter) {
    expect(true)->toBeTrue();
    $runCounter++;

    return 'second';
});

test('depends', function () {
    expect(func_get_args())->toBe(['first', 'second']);
})->depends('first', 'second');

test('depends with ...params', function (string ...$params) {
    expect(func_get_args())->toBe($params);
})->depends('first', 'second');

test('depends with defined arguments', function (string $first, string $second) {
    expect($first)->toBe('first');
    expect($second)->toBe('second');
})->depends('first', 'second');

test('depends run test only once', function () use (&$runCounter) {
    expect($runCounter)->toBe(2);
})->depends('first', 'second');

// Regression tests. See https://github.com/pestphp/pest/pull/216
it('asserts true is true')->assertTrue(true);
test('depends works with the correct test name')->assertTrue(true)->depends('it asserts true is true');
