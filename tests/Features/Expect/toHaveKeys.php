<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(['a' => 1, 'b', 'c' => 'world'])->toHaveKeys(['a', 'c']);
});

test('failures', function () {
    expect(['a' => 1, 'b', 'c' => 'world'])->toHaveKeys(['a', 'd']);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(['a' => 1, 'hello' => 'world', 'c'])->not->toHaveKeys(['hello', 'c']);
})->throws(ExpectationFailedException::class);
