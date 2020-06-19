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

test('dependsOn', function () {
    assertEquals(
        ['first', 'second'],
        func_get_args()
    );
})->dependsOn('first', 'second');

test('dependsOn with ...params', function (string ...$params) {
    assertEquals(
        ['first', 'second'],
        $params
    );
})->dependsOn('first', 'second');

test('dependsOn with defined arguments', function (string $first, string $second) {
    assertEquals('first', $first);
    assertEquals('second', $second);
})->dependsOn('first', 'second');

test('dependsOn run test only once', function () use (&$runCounter) {
    assertEquals(2, $runCounter);
})->dependsOn('first', 'second');

test('depends alias for dependsOn', function (string ...$params) {
    assertEquals(
        ['first', 'second'],
        $params
    );
})->depends('first', 'second');
