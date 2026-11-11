<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(log(0))->toBeInfinite()
        ->and(log(1))->not->toBeInfinite();
});

test('failures', function (): void {
    expect(asin(2))->toBeInfinite();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(asin(2))->toBeInfinite('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(INF)->not->toBeInfinite();
})->throws(ExpectationFailedException::class);
