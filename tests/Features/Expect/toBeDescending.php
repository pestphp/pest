<?php

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass with integers', function () {
    expect([3, 2, 1])->toBeDescending();
});

test('pass with equal adjacent values', function () {
    expect([3, 2, 2, 1])->toBeDescending();
});

test('pass with strings', function () {
    expect(['cherry', 'banana', 'apple'])->toBeDescending();
});

test('pass with floats', function () {
    expect([3.3, 2.2, 1.1])->toBeDescending();
});

test('pass with single element', function () {
    expect([42])->toBeDescending();
});

test('pass with empty array', function () {
    expect([])->toBeDescending();
});

test('failures', function () {
    expect([1, 2, 3])->toBeDescending();
})->throws(ExpectationFailedException::class, 'Array is not sorted in descending order.');

test('failures with custom message', function () {
    expect([1, 2, 3])->toBeDescending('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with invalid type', function () {
    expect('not an array')->toBeDescending();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [array]');

test('failures with mixed types', function () {
    expect([3, 'two', 1])->toBeDescending();
})->throws(InvalidArgumentException::class, 'Array values must all be of the same comparable type.');

test('not pass', function () {
    expect([1, 2, 3])->not->toBeDescending();
});

test('not failures', function () {
    expect([3, 2, 1])->not->toBeDescending();
})->throws(ExpectationFailedException::class);
