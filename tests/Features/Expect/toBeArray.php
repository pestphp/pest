<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect([1, 2, 3])->toBeArray()
        ->and('1, 2, 3')->not->toBeArray();
});

test('failures', function (): void {
    expect()->toBeArray();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect()->toBeArray('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(['a', 'b', 'c'])->not->toBeArray();
})->throws(ExpectationFailedException::class);
