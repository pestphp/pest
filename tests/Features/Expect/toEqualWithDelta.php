<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(1.0)->toEqualWithDelta(1.3, .4);
});

test('failures with custom message', function (): void {
    expect(1.0)->toEqualWithDelta(1.5, .1, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(1.0)->not->toEqualWithDelta(1.6, .7);
})->throws(ExpectationFailedException::class);
