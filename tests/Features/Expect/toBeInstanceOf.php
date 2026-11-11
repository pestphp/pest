<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(new Exception)->toBeInstanceOf(Exception::class)->not->toBeInstanceOf(RuntimeException::class);
});

test('failures', function (): void {
    expect(new Exception)->toBeInstanceOf(RuntimeException::class);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(new Exception)->toBeInstanceOf(RuntimeException::class, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(new Exception)->not->toBeInstanceOf(Exception::class);
})->throws(ExpectationFailedException::class);
