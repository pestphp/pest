<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(42)->toBeNumeric()
        ->and('A')->not->toBeNumeric();
});

test('failures', function (): void {
    expect()->toBeNumeric();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect()->toBeNumeric('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(6 * 7)->not->toBeNumeric();
})->throws(ExpectationFailedException::class);
