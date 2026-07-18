<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('UPPERCASE')->toBeUppercase()
        ->and('lowercase')->not->toBeUppercase();
});

test('failures', function (): void {
    expect('lowercase')->toBeUppercase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('lowercase')->toBeUppercase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('UPPERCASE')->not->toBeUppercase();
})->throws(ExpectationFailedException::class);
