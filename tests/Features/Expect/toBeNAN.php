<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(asin(2))->toBeNan()
        ->and(log(0))->not->toBeNan();
});

test('failures', function (): void {
    expect(1)->toBeNan();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(1)->toBeNan('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(acos(1.5))->not->toBeNan();
})->throws(ExpectationFailedException::class);
