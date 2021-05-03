<?php

global $globalHook;

uses()->beforeAll(function () use ($globalHook) {
    expect($globalHook)
        ->toHaveProperty('beforeAll')
        ->and($globalHook->beforeAll)
        ->toBe(0);

    $globalHook->beforeAll = 1;
});

beforeAll(function () use ($globalHook) {
    expect($globalHook)
        ->toHaveProperty('beforeAll')
        ->and($globalHook->beforeAll)
        ->toBe(1);

    $globalHook->beforeAll = 2;
});

test('global beforeAll execution order', function () use ($globalHook) {
    expect($globalHook)
        ->toHaveProperty('beforeAll')
        ->and($globalHook->beforeAll)
        ->toBe(2);
});
