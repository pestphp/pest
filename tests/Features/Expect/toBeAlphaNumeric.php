<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('abc123')->toBeAlphaNumeric()
        ->and('-')->not->toBeAlphaNumeric();
});

test('failures', function (): void {
    expect('-')->toBeAlphaNumeric();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('-')->toBeAlphaNumeric('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('abc123')->not->toBeAlphaNumeric();
})->throws(ExpectationFailedException::class);
