<?php

$foo             = new stdClass();
$foo->beforeAll  = false;
$foo->beforeEach = false;
$foo->afterEach  = false;
$foo->afterAll   = false;

beforeAll(fn () => $foo->beforeAll = true);
beforeEach(fn () => $foo->beforeEach = true);
afterEach(fn () => $foo->afterEach = true);
afterAll(fn () => $foo->afterAll = true);

register_shutdown_function(function () use ($foo) {
    assertFalse($foo->beforeAll);
    assertFalse($foo->beforeEach);
    assertFalse($foo->afterEach);
    assertFalse($foo->afterAll);
});
