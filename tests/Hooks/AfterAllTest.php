<?php

use function PHPUnit\Framework\assertSame;

$_SERVER['globalHook']->calls->afterAllInFile = 0;
$_SERVER['globalHook']->calls->afterAllTestsRan = 0;

pest()->afterAll(function () {
    $_SERVER['globalHook']->calls->afterAllInFile++;
});

it('does not get called before all tests 1', function () {
    $_SERVER['globalHook']->calls->afterAllTestsRan++;
    expect($_SERVER['globalHook']->calls->afterAllInFile)->toBe(0);
})->repeat(2);

it('does not get called before all tests 2', function () {
    $_SERVER['globalHook']->calls->afterAllTestsRan++;
    expect($_SERVER['globalHook']->calls->afterAllInFile)->toBe(0);
});

register_shutdown_function(function () {
    // Under --parallel, Pest also evaluates this file in the parent process to
    // build the suite; the tests (and therefore afterAll) only run in the worker.
    // Skip the assertion in any process where no test of this file actually ran.
    if ($_SERVER['globalHook']->calls->afterAllTestsRan === 0) {
        return;
    }

    assertSame(1, $_SERVER['globalHook']->calls->afterAllInFile, 'Expected the [pest()->afterAll()] hook to have been called exactly once.');
});
