<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect([1, 2, 3])->toEqualCanonicalizing([3, 1, 2])
        ->and(['g', 'a', 'z'])->not->toEqualCanonicalizing(['a', 'z']);
});

test('failures', function (): void {
    expect([3, 2, 1])->toEqualCanonicalizing([1, 2]);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect([3, 2, 1])->toEqualCanonicalizing([1, 2], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(['a', 'b', 'c'])->not->toEqualCanonicalizing(['b', 'a', 'c']);
})->throws(ExpectationFailedException::class);
