<?php

test('an exception is thrown if the the type is not iterable', function () {
    expect('Foobar')->each->sequence();
})->throws(BadMethodCallException::class, 'Expectation value is not iterable.');

test('an exception is thrown if there are no expectations', function () {
    expect(['Foobar'])->sequence();
})->throws(InvalidArgumentException::class, 'No sequence expectations defined.');

test('allows for sequences of checks to be run on iterable data', function () {
    expect([1, 2, 3])
        ->sequence(
            function ($expectation) {
                $expectation->toBeInt()->toEqual(1);
            },
            function ($expectation) {
                $expectation->toBeInt()->toEqual(2);
            },
            function ($expectation) {
                $expectation->toBeInt()->toEqual(3);
            },
        );

    expect(static::getCount())->toBe(6);
});

test('loops back to the start if it runs out of sequence items', function () {
    expect([1, 2, 3, 1, 2, 3, 1, 2])
        ->sequence(
            function ($expectation) {
                $expectation->toBeInt()->toEqual(1);
            },
            function ($expectation) {
                $expectation->toBeInt()->toEqual(2);
            },
            function ($expectation) {
                $expectation->toBeInt()->toEqual(3);
            },
        );

    expect(static::getCount())->toBe(16);
});

test('fails if the number of iterable items is less than the number of expectations', function () {
    expect([1, 2])
        ->sequence(
            function ($expectation) {
                $expectation->toBeInt()->toEqual(1);
            },
            function ($expectation) {
                $expectation->toBeInt()->toEqual(2);
            },
            function ($expectation) {
                $expectation->toBeInt()->toEqual(3);
            },
        );
})->throws(OutOfRangeException::class, 'Sequence expectations are more than the iterable items.');

test('it works with associative arrays', function () {
    expect(['foo' => 'bar', 'baz' => 'boom'])
        ->sequence(
            function ($expectation, $key) {
                $expectation->toEqual('bar');
                $key->toEqual('foo');
            },
            function ($expectation, $key) {
                $expectation->toEqual('boom');
                $key->toEqual('baz');
            },
        );
});

test('it can be passed non-callable values', function () {
    expect(['foo', 'bar', 'baz'])->sequence('foo', 'bar', 'baz');

    expect(static::getCount())->toBe(3);
});

test('it can be passed a mixture of value types', function () {
    expect(['foo', 'bar', 'baz'])->sequence(
        'foo',
        function ($expectation) {
            $expectation->toEqual('bar')->toBeString();
        },
        'baz'
    );

    expect(static::getCount())->toBe(4);
});

test('it works with traversables', function () {
    $generator = (function () {
        yield 'one' => (fn () => yield from [1, 2, 3])();
        yield 'two' => (fn () => yield from [4, 5, 6])();
        yield 'three' => (fn () => yield from [7, 8, 9])();
    })();

    expect($generator)->sequence(
        fn ($value, $key) => $key->toBe('one')
            ->and($value)
            ->toBeInstanceOf(Generator::class)
            ->sequence(1, 2, 3),
        fn ($value, $key) => $key->toBe('two')
            ->and($value)
            ->toBeInstanceOf(Generator::class)
            ->sequence(4, 5, 6),
        fn ($value, $key) => $key->toBe('three')
            ->and($value)
            ->toBeInstanceOf(Generator::class)
            ->sequence(7, 8, 9),
    );
});
