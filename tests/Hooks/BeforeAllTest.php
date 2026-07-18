<?php

declare(strict_types=1);

pest()->beforeAll(function (): void {
    expect($_SERVER['globalHook']->calls->beforeAll)
        ->toBe(0);

    $_SERVER['globalHook']->calls->beforeAll++;
});

it('gets called before all tests 1', function (): void {
    expect($_SERVER['globalHook']->calls->beforeAll)->toBe(1);
})->repeat(2);

it('gets called before all tests 2', function (): void {
    expect($_SERVER['globalHook']->calls->beforeAll)->toBe(1);
});
