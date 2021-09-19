<?php

use PHPUnit\Framework\ExpectationFailedException;

test('allows to run on non-iterable data', function () {
    expect('foo')
        ->to(
            function ($value) { return str_replace('foo', 'bar', $value); }
        )
        ->toBe('bar');

    expect(static::getCount())->toBe(2);
});

test('fails if iterable items are used for the non-iterable value', function () {
    expect('foo')
        ->to(
            function ($value) { return $value; },
            function ($value) { return $value; },
        );
})->throws(ExpectationFailedException::class);

test('allows it to be run on iterable data', function () {
    expect([1, 2, 3])
        ->to(
            function ($value) { return $value + 1; },
            function ($value) { return $value + 1; },
            function ($value) { return $value + 1; },
        )
        ->toMatchArray([2, 3, 4]);

    expect(static::getCount())->toBe(6);
});

test('loops back to the start if it runs out of items', function () {
    expect([1, 2, 3, 1, 2, 3, 1, 2])
        ->to(
            function ($value) { return $value + 1; },
            function ($value) { return $value + 1; },
            function ($value) { return $value + 1; },
        )
    ->toMatchArray([2, 3, 4, 2, 3, 4, 2, 3]);

    expect(static::getCount())->toBe(16);
});

test('fails if the number of iterable items is greater than the number of values', function () {
    expect([1, 2])
        ->to(
            function ($value) { return $value; },
            function ($value) { return $value; },
            function ($value) { return $value; },
        );
})->throws(ExpectationFailedException::class);

test('it can be passed non-callable values', function () {
    expect(['foo', 'bar', 'baz'])
        ->to('pest', 'php', 'com')
        ->toMatchArray(['pest', 'php', 'com']);

    expect(static::getCount())->toBe(6);
});

test('it can be passed a mixture of value types', function () {
    expect(['foo', 'bar', 'baz'])->to(
        'foo',
        function ($value) { return str_replace('bar', 'pest', $value); },
        'baz'
    )
    ->toMatchArray(['foo', 'pest', 'baz']);

    expect(static::getCount())->toBe(6);
});
