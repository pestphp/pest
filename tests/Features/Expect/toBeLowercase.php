<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('lowercase')->toBeLowercase()
        ->and('UPPERCASE')->not->toBeLowercase();
});

test('failures', function (): void {
    expect('UPPERCASE')->toBeLowercase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('UPPERCASE')->toBeLowercase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('lowercase')->not->toBeLowercase();
})->throws(ExpectationFailedException::class);
