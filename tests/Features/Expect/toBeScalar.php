<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(1.1)->toBeScalar();
});

test('failures', function (): void {
    expect()->toBeScalar();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect()->toBeScalar('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(42)->not->toBeScalar();
})->throws(ExpectationFailedException::class);
