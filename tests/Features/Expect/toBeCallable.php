<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(function (): void {})->toBeCallable()
        ->and(null)->not->toBeCallable();
});

test('failures', function (): void {
    $hello = 5;

    expect($hello)->toBeCallable();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    $hello = 5;

    expect($hello)->toBeCallable('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(fn (): int => 42)->not->toBeCallable();
})->throws(ExpectationFailedException::class);
