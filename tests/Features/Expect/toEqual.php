<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('00123')->toEqual(123);
});

test('failures', function (): void {
    expect(['a', 'b', 'c'])->toEqual(['a', 'b']);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(['a', 'b', 'c'])->toEqual(['a', 'b'], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('042')->not->toEqual(42);
})->throws(ExpectationFailedException::class);
