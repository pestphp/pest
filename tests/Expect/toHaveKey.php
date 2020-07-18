<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(['a' => 1, 'b', 'c' => 'world'])->toHaveKey('c');
});

test('failures', function () {
    expect(['a' => 1, 'b', 'c' => 'world'])->toHaveKey('hello');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(['a' => 1, 'hello' => 'world', 'c'])->not->toHaveKey('hello');
})->throws(ExpectationFailedException::class);
