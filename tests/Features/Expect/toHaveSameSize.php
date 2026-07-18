<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('failures with wrong type', function (): void {
    expect('foo')->toHaveSameSize([1]);
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [countable|iterable].');

test('pass', function (): void {
    expect([1, 2, 3])->toHaveSameSize([4, 5, 6]);
});

test('failures', function (): void {
    expect([1, 2, 3])->toHaveSameSize([1]);
})->throws(ExpectationFailedException::class);

test('failures with message', function (): void {
    expect([1, 2, 3])->toHaveSameSize([1], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect([1, 2, 3])->not->toHaveSameSize([1]);
});
