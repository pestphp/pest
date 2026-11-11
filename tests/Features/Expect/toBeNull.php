<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect()->toBeNull()
        ->and('')->not->toBeNull();
});

test('failures', function (): void {
    expect('hello')->toBeNull();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('hello')->toBeNull('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect()->not->toBeNull();
})->throws(ExpectationFailedException::class);
