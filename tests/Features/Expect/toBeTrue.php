<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('strict comparisons', function (): void {
    expect(true)->toBeTrue();
});

test('failures', function (): void {
    expect('')->toBeTrue();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('')->toBeTrue('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(false)->toBeTrue();
})->throws(ExpectationFailedException::class);
