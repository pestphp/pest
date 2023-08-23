<?php

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('failures with wrong type', function () {
    expect('foo')->toHaveSameSize([1]);
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [countable|iterable].');

test('pass', function () {
    expect([1, 2, 3])->toHaveSameSize([4, 5, 6]);
});

test('failures', function () {
    expect([1, 2, 3])->toHaveSameSize([1]);
})->throws(ExpectationFailedException::class);

test('failures with message', function () {
    expect([1, 2, 3])->toHaveSameSize([1], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect([1, 2, 3])->not->toHaveSameSize([1]);
});
