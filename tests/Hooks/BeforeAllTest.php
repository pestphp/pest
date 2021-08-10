<?php

use Pest\Support\Str;
use Pest\TestSuite;

global $globalHook;

// HACK: we have to determine our $globalHook->calls baseline. This is because
// two other tests are executed before this one due to filename ordering.
$args   = $_SERVER['argv'] ?? [];
$single = (isset($args[1]) && Str::endsWith(__FILE__, $args[1])) || TestSuite::getInstance()->isInParallel;
$offset = $single ? 0 : 2;

uses()->beforeAll(function () use ($globalHook, $offset) {
    expect($globalHook)
        ->toHaveProperty('beforeAll')
        ->and($globalHook->beforeAll)
        ->toBe(0)
        ->and($globalHook->calls)
        ->beforeAll
        ->toBe(1 + $offset);

    $globalHook->beforeAll = 1;
    $globalHook->calls->beforeAll++;
});

beforeAll(function () use ($globalHook, $offset) {
    expect($globalHook)
        ->toHaveProperty('beforeAll')
        ->and($globalHook->beforeAll)
        ->toBe(1)
        ->and($globalHook->calls)
        ->beforeAll
        ->toBe(2 + $offset);

    $globalHook->beforeAll = 2;
    $globalHook->calls->beforeAll++;
});

test('global beforeAll execution order', function () use ($globalHook, $offset) {
    expect($globalHook)
        ->toHaveProperty('beforeAll')
        ->and($globalHook->beforeAll)
        ->toBe(2)
        ->and($globalHook->calls)
        ->beforeAll
        ->toBe(3 + $offset);
});

it('only gets called once per file', function () use ($globalHook, $offset) {
    expect($globalHook)
        ->beforeAll
        ->toBe(2)
        ->and($globalHook->calls)
        ->beforeAll
        ->toBe(3 + $offset);
});
