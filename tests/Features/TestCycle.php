<?php

use function PHPUnit\Framework\assertFalse;

$foo = new stdClass;
$foo->beforeAll = false;
$foo->beforeEach = false;
$foo->afterEach = false;
$foo->afterAll = false;

beforeAll(function () use ($foo) {
    $foo->beforeAll = true;
});
beforeEach(function () use ($foo) {
    $foo->beforeEach = true;
});
afterEach(function () use ($foo) {
    $foo->afterEach = true;
});
afterAll(function () use ($foo) {
    $foo->afterAll = true;
});

register_shutdown_function(function () use ($foo) {
    assertFalse($foo->beforeAll);
    assertFalse($foo->beforeEach);
    assertFalse($foo->afterEach);
    assertFalse($foo->afterAll);
});
