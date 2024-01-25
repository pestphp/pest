<?php

uses()->afterAll(function () {
    expect($_SERVER['globalHook'])
        ->toHaveProperty('afterAll')
        ->and($_SERVER['globalHook']->afterAll)
        ->toBe(1)
        ->and($_SERVER['globalHook']->calls)
        ->afterAll
        ->toBe(1);

    $_SERVER['globalHook']->afterAll = 2;
    $_SERVER['globalHook']->calls->afterAll++;
});

afterAll(function () {
    expect($_SERVER['globalHook'])
        ->toHaveProperty('afterAll')
        ->and($_SERVER['globalHook']->afterAll)
        ->toBe(1)
        ->and($_SERVER['globalHook']->calls)
        ->afterAll
        ->toBe(2);

    $_SERVER['globalHook']->afterAll = 2;
    $_SERVER['globalHook']->calls->afterAll++;
});

test('global afterAll execution order', function () {
    expect($_SERVER['globalHook'])
        ->not()
        ->toHaveProperty('afterAll')
        ->and($_SERVER['globalHook']->calls)
        ->afterAll
        ->toBe(0);
});

it('only gets called once per file', function () {
    expect($_SERVER['globalHook'])
        ->not()
        ->toHaveProperty('afterAll')
        ->and($_SERVER['globalHook']->calls)
        ->afterAll
        ->toBe(0);
});
