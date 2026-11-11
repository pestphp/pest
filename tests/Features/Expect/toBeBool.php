<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(true)->toBeBool()
        ->and(0)->not->toBeBool();
});

test('failures', function (): void {
    expect()->toBeBool();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect()->toBeBool('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(false)->not->toBeBool();
})->throws(ExpectationFailedException::class);
