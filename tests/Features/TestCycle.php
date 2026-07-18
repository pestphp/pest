<?php

use function PHPUnit\Framework\assertFalse;

$foo = new stdClass;
$foo->beforeAll = false;
$foo->beforeEach = false;
$foo->afterEach = false;
$foo->afterAll = false;

beforeAll(function () use ($foo): void {
    $foo->beforeAll = true;
});
beforeEach(function () use ($foo): void {
    $foo->beforeEach = true;
});
afterEach(function () use ($foo): void {
    $foo->afterEach = true;
});
afterAll(function () use ($foo): void {
    $foo->afterAll = true;
});

register_shutdown_function(function () use ($foo): void {
    assertFalse($foo->beforeAll);
    assertFalse($foo->beforeEach);
    assertFalse($foo->afterEach);
    assertFalse($foo->afterAll);
});
