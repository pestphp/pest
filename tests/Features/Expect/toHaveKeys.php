<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => 'baz']])->toHaveKeys(['a', 'c', 'foo.bar']);
});

test('failures', function () {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => 'baz']])->toHaveKeys(['a', 'd', 'foo.bar', 'hello.world']);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => 'baz']])->not->toHaveKeys(['foo.bar', 'c', 'z']);
})->throws(ExpectationFailedException::class);
