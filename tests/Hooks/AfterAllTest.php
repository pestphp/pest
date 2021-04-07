<?php

global $globalHook;

uses()->afterAll(function () use ($globalHook) {
    expect($globalHook)
        ->toHaveProperty('afterAll')
        ->and($globalHook->afterAll)
        ->toBe(0);

    $globalHook->afterAll = 1;
});

afterAll(function () use ($globalHook) {
    expect($globalHook)
        ->toHaveProperty('afterAll')
        ->and($globalHook->afterAll)
        ->toBe(1);

    $globalHook->afterAll = 2;
});

test('global afterAll execution order', function () use ($globalHook) {
    expect($globalHook)
        ->not()
        ->toHaveProperty('afterAll');
});
