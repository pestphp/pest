<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(1.0)->toBeFloat()
        ->and(1)->not->toBeFloat();
});

test('failures', function (): void {
    expect(42)->toBeFloat();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(42)->toBeFloat('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(log(3))->not->toBeFloat();
})->throws(ExpectationFailedException::class);
