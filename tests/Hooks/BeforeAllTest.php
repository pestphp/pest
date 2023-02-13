<?php

use Pest\Plugins\Parallel;
use Pest\Support\Str;

// HACK: we have to determine our $_SERVER['globalHook-]>calls baseline. This is because
// two other tests are executed before this one due to filename ordering.
$args = $_SERVER['argv'] ?? [];
$single = (isset($args[1]) && Str::endsWith(__FILE__, $args[1])) || Parallel::isWorker();
$offset = $single ? 0 : 2;

uses()->beforeAll(function () use ($offset) {
    expect($_SERVER['globalHook'])
        ->toHaveProperty('beforeAll')
        ->and($_SERVER['globalHook']->beforeAll)
        ->toBe(0)
        ->and($_SERVER['globalHook']->calls)
        ->beforeAll
        ->toBe(1 + $offset);

    $_SERVER['globalHook']->beforeAll = 1;
    $_SERVER['globalHook']->calls->beforeAll++;
});

beforeAll(function () use ($offset) {
    expect($_SERVER['globalHook'])
        ->toHaveProperty('beforeAll')
        ->and($_SERVER['globalHook']->beforeAll)
        ->toBe(1)
        ->and($_SERVER['globalHook']->calls)
        ->beforeAll
        ->toBe(2 + $offset);

    $_SERVER['globalHook']->beforeAll = 2;
    $_SERVER['globalHook']->calls->beforeAll++;
});

test('global beforeAll execution order', function () use ($offset) {
    expect($_SERVER['globalHook'])
        ->toHaveProperty('beforeAll')
        ->and($_SERVER['globalHook']->beforeAll)
        ->toBe(2)
        ->and($_SERVER['globalHook']->calls)
        ->beforeAll
        ->toBe(3 + $offset);
});

it('only gets called once per file', function () use ($offset) {
    expect($_SERVER['globalHook'])
        ->beforeAll
        ->toBe(2)
        ->and($_SERVER['globalHook']->calls)
        ->beforeAll
        ->toBe(3 + $offset);
});
