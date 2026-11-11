<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => 'baz']])->toHaveKeys(['a', 'c', 'foo.bar']);
});

test('pass with multi-dimensional arrays', function (): void {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => ['bir' => 'biz']]])->toHaveKeys(['a', 'c', 'foo' => ['bar' => ['bir']]]);
});

test('failures', function (): void {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => 'baz']])->toHaveKeys(['a', 'd', 'foo.bar', 'hello.world']);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => 'baz']])->toHaveKeys(['a', 'd', 'foo.bar', 'hello.world'], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with multi-dimensional arrays', function (): void {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => ['bir' => 'biz']]])->toHaveKeys(['a', 'd', 'foo' => ['bar' => 'bir'], 'hello.world']);
})->throws(ExpectationFailedException::class);

test('failures with multi-dimensional arrays and custom message', function (): void {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => ['bir' => 'biz']]])->toHaveKeys(['a', 'd', 'foo' => ['bar' => 'bir'], 'hello.world'], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => 'baz']])->not->toHaveKeys(['foo.bar', 'c', 'z']);
})->throws(ExpectationFailedException::class);

test('not failures with multi-dimensional arrays', function (): void {
    expect(['a' => 1, 'b', 'c' => 'world', 'foo' => ['bar' => ['bir' => 'biz']]])->not->toHaveKeys(['foo' => ['bar' => 'bir'], 'c', 'z']);
})->throws(ExpectationFailedException::class);
