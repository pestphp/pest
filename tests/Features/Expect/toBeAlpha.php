<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('abc')->toBeAlpha()
        ->and('123')->not->toBeAlpha();
});

test('failures', function (): void {
    expect('123')->toBeAlpha();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('123')->toBeAlpha('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('abc')->not->toBeAlpha();
})->throws(ExpectationFailedException::class);
