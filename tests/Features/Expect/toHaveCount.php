<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect([1, 2, 3])->toHaveCount(3);
});

test('failures with invalid type', function (): void {
    expect('foo')->toHaveCount(3);
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [countable|iterable]');

test('failures', function (): void {
    expect([1, 2, 3])->toHaveCount(4);
})->throws(ExpectationFailedException::class);

test('failures with message', function (): void {
    expect([1, 2, 3])->toHaveCount(4, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect([1, 2, 3])->not->toHaveCount(3);
})->throws(ExpectationFailedException::class);
