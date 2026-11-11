<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('user@example.com')->toBeEmail()
        ->and('notanemail')->not->toBeEmail();
});

test('failures', function (): void {
    expect('notanemail')->toBeEmail();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('notanemail')->toBeEmail('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with default message', function (): void {
    expect('notanemail')->toBeEmail();
})->throws(ExpectationFailedException::class, 'Failed asserting that notanemail is an email address.');

test('not failures', function (): void {
    expect('user@example.com')->not->toBeEmail();
})->throws(ExpectationFailedException::class);
