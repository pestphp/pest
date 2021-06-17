<?php

test('an exception is thrown if the the type is not iterable', function () {
    expect('Foobar')->each->sequence();
})->throws(BadMethodCallException::class, 'Expectation value is not iterable.');

test('allows for sequences of checks to be run on iterable data', function () {
    expect([1, 2, 3])
        ->sequence(
            function ($expectation) { $expectation->toBeInt()->toEqual(1); },
            function ($expectation) { $expectation->toBeInt()->toEqual(2); },
            function ($expectation) { $expectation->toBeInt()->toEqual(3); },
        );

    expect(static::getCount())->toBe(6);
});

test('loops back to the start if it runs out of sequence items', function () {
    expect([1, 2, 3, 1, 2, 3, 1, 2])
        ->sequence(
            function ($expectation) { $expectation->toBeInt()->toEqual(1); },
            function ($expectation) { $expectation->toBeInt()->toEqual(2); },
            function ($expectation) { $expectation->toBeInt()->toEqual(3); },
        );

    expect(static::getCount())->toBe(16);
});

test('it works if the number of items in the iterable is smaller than the number of expectations', function () {
    expect([1, 2])
        ->sequence(
            function ($expectation) { $expectation->toBeInt()->toEqual(1); },
            function ($expectation) { $expectation->toBeInt()->toEqual(2); },
            function ($expectation) { $expectation->toBeInt()->toEqual(3); },
        );

    expect(static::getCount())->toBe(4);
});

test('it works with associative arrays', function () {
    expect(['foo' => 'bar', 'baz' => 'boom'])
        ->sequence(
            function ($expectation, $key) { $expectation->toEqual('bar'); $key->toEqual('foo'); },
            function ($expectation, $key) { $expectation->toEqual('boom'); $key->toEqual('baz'); },
        );
});
