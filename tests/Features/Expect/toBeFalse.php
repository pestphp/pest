<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('strict comparisons', function (): void {
    expect(false)->toBeFalse();
});

test('failures', function (): void {
    expect('')->toBeFalse();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('')->toBeFalse('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(false)->toBeTrue();
})->throws(ExpectationFailedException::class);
