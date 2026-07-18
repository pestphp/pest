<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(42)->toBeInt()
        ->and(42.0)->not->toBeInt();
});

test('failures', function (): void {
    expect(42.0)->toBeInt();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(42.0)->toBeInt('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(6 * 7)->not->toBeInt();
})->throws(ExpectationFailedException::class);
