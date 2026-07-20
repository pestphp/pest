<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(5)->toBePositive()
        ->and(0.5)->toBePositive()
        ->and(0)->not->toBePositive()
        ->and(-1)->not->toBePositive();
});

test('failures', function (): void {
    expect(0)->toBePositive();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(0)->toBePositive('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(10)->not->toBePositive();
})->throws(ExpectationFailedException::class);
