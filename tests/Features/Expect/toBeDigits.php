<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('123')->toBeDigits()
        ->and('123.14')->not->toBeDigits();
});

test('failures', function (): void {
    expect('123.14')->toBeDigits();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('123.14')->toBeDigits('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('445')->not->toBeDigits();
})->throws(ExpectationFailedException::class);
