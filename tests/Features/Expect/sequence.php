<?php

test('an exception is thrown if the the type is not iterable', function (): void {
    expect('Foobar')->each->sequence();
})->throws(BadMethodCallException::class, 'Expectation value is not iterable.');

test('an exception is thrown if there are no expectations', function (): void {
    expect(['Foobar'])->sequence();
})->throws(InvalidArgumentException::class, 'No sequence expectations defined.');

test('allows for sequences of checks to be run on iterable data', function (): void {
    expect([1, 2, 3])
        ->sequence(
            function ($expectation): void {
                $expectation->toBeInt()->toEqual(1);
            },
            function ($expectation): void {
                $expectation->toBeInt()->toEqual(2);
            },
            function ($expectation): void {
                $expectation->toBeInt()->toEqual(3);
            },
        )
        ->and(static::getCount())->toBe(6);
});

test('loops back to the start if it runs out of sequence items', function (): void {
    expect([1, 2, 3, 1, 2, 3, 1, 2])
        ->sequence(
            function ($expectation): void {
                $expectation->toBeInt()->toEqual(1);
            },
            function ($expectation): void {
                $expectation->toBeInt()->toEqual(2);
            },
            function ($expectation): void {
                $expectation->toBeInt()->toEqual(3);
            },
        )
        ->and(static::getCount())->toBe(16);
});

test('fails if the number of iterable items is less than the number of expectations', function (): void {
    expect([1, 2])
        ->sequence(
            function ($expectation): void {
                $expectation->toBeInt()->toEqual(1);
            },
            function ($expectation): void {
                $expectation->toBeInt()->toEqual(2);
            },
            function ($expectation): void {
                $expectation->toBeInt()->toEqual(3);
            },
        );
})->throws(OutOfRangeException::class, 'Sequence expectations are more than the iterable items.');

test('it works with associative arrays', function (): void {
    expect(['foo' => 'bar', 'baz' => 'boom'])
        ->sequence(
            function ($expectation, $key): void {
                $expectation->toEqual('bar');
                $key->toEqual('foo');
            },
            function ($expectation, $key): void {
                $expectation->toEqual('boom');
                $key->toEqual('baz');
            },
        );
});

test('it can be passed non-callable values', function (): void {
    expect(['foo', 'bar', 'baz'])->sequence('foo', 'bar', 'baz')
        ->and(static::getCount())->toBe(3);
});

test('it can be passed a mixture of value types', function (): void {
    expect(['foo', 'bar', 'baz'])->sequence(
        'foo',
        function ($expectation): void {
            $expectation->toEqual('bar')->toBeString();
        },
        'baz'
    )
        ->and(static::getCount())->toBe(4);
});

test('it works with traversables', function (): void {
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
