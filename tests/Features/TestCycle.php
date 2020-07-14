<?php

$foo             = new stdClass();
$foo->beforeAll  = false;
$foo->beforeEach = false;
$foo->afterEach  = false;
$foo->afterAll   = false;

beforeAll(function () {
    $foo->beforeAll = true;
});
beforeEach(function () {
    $foo->beforeEach = true;
});
afterEach(function () {
    $foo->afterEach = true;
});
afterAll(function () {
    $foo->afterAll = true;
});

register_shutdown_function(function () use ($foo) {
    expect($foo->beforeAll)->toBeFalse();
    expect($foo->beforeEach)->toBeFalse();
    expect($foo->afterEach)->toBeFalse();
    expect($foo->afterAll)->toBeFalse();
});
