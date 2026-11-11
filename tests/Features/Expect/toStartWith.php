<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('username')->toStartWith('user');
});

test('failures', function (): void {
    expect('username')->toStartWith('password');
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('username')->toStartWith('password', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('username')->not->toStartWith('user');
})->throws(ExpectationFailedException::class);
