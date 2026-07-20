<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(1)->toBeOdd()
        ->and(-7)->toBeOdd()
        ->and(2)->not->toBeOdd()
        ->and(0)->not->toBeOdd();
});

test('failures', function (): void {
    expect(2)->toBeOdd();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(2)->toBeOdd('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(7)->not->toBeOdd();
})->throws(ExpectationFailedException::class);
