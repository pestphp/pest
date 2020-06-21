<?php

$runCounter = 0;

test('first', function () use (&$runCounter) {
    assertTrue(true);
    $runCounter++;

    return 'first';
});

test('second', function () use (&$runCounter) {
    assertTrue(true);
    $runCounter++;

    return 'second';
});

test('depends', function () {
    assertEquals(
        ['first', 'second'],
        func_get_args()
    );
})->depends('first', 'second');

test('depends with ...params', function (string ...$params) {
    assertEquals(
        ['first', 'second'],
        $params
    );
})->depends('first', 'second');

test('depends with defined arguments', function (string $first, string $second) {
    assertEquals('first', $first);
    assertEquals('second', $second);
})->depends('first', 'second');

test('depends run test only once', function () use (&$runCounter) {
    assertEquals(2, $runCounter);
})->depends('first', 'second');
