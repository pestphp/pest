<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('username')->toEndWith('name');
});

test('failures', function (): void {
    expect('username')->toEndWith('password');
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('username')->toEndWith('password', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('username')->not->toEndWith('name');
})->throws(ExpectationFailedException::class);
