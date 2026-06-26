<?php

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass with integers', function () {
    expect([1, 2, 3])->toBeAscending();
});

test('pass with equal adjacent values', function () {
    expect([1, 2, 2, 3])->toBeAscending();
});

test('pass with strings', function () {
    expect(['apple', 'banana', 'cherry'])->toBeAscending();
});

test('pass with floats', function () {
    expect([1.1, 2.2, 3.3])->toBeAscending();
});

test('pass with single element', function () {
    expect([42])->toBeAscending();
});

test('pass with empty array', function () {
    expect([])->toBeAscending();
});

test('failures', function () {
    expect([3, 1, 2])->toBeAscending();
})->throws(ExpectationFailedException::class, 'Array is not sorted in ascending order.');

test('failures with custom message', function () {
    expect([3, 1, 2])->toBeAscending('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with invalid type', function () {
    expect('not an array')->toBeAscending();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [array]');

test('failures with mixed types', function () {
    expect([1, 'two', 3])->toBeAscending();
})->throws(InvalidArgumentException::class, 'Array values must all be of the same comparable type.');

test('not pass', function () {
    expect([3, 1, 2])->not->toBeAscending();
});

test('not failures', function () {
    expect([1, 2, 3])->not->toBeAscending();
})->throws(ExpectationFailedException::class);
