<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(-5)->toBeNegative()
        ->and(-0.5)->toBeNegative()
        ->and(0)->not->toBeNegative()
        ->and(5)->not->toBeNegative();
});

test('failures', function (): void {
    expect(0)->toBeNegative();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(0)->toBeNegative('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(-10)->not->toBeNegative();
})->throws(ExpectationFailedException::class);
