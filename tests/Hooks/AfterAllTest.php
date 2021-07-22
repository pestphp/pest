<?php

global $globalHook;

// NOTE: this test does not have a $globalHook->calls offset since it is first
// in the directory and thus will always run before the others. See also the
// BeforeAllTest.php for details.

uses()->afterAll(function () use ($globalHook) {
    expect($globalHook)
        ->toHaveProperty('afterAll')
        ->and($globalHook->afterAll)
        ->toBe(0)
        ->and($globalHook->calls)
        ->afterAll
        ->toBe(1);

    $globalHook->afterAll = 1;
    $globalHook->calls->afterAll++;
});

afterAll(function () use ($globalHook) {
    expect($globalHook)
        ->toHaveProperty('afterAll')
        ->and($globalHook->afterAll)
        ->toBe(1)
        ->and($globalHook->calls)
        ->afterAll
        ->toBe(2);

    $globalHook->afterAll = 2;
    $globalHook->calls->afterAll++;
});

test('global afterAll execution order', function () use ($globalHook) {
    expect($globalHook)
        ->not()
        ->toHaveProperty('afterAll')
        ->and($globalHook->calls)
        ->afterAll
        ->toBe(0);
});

test('it only gets called once per file', function () use ($globalHook) {
    expect($globalHook)
        ->not()
        ->toHaveProperty('afterAll')
        ->and($globalHook->calls)
        ->afterAll
        ->toBe(0);
});
