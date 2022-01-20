<?php

use Pest\Expectation;

test('an exception is thrown if the the type is not iterable', function () {
    expect('Foobar')->each()->toEqual('Foobar');
})->throws(BadMethodCallException::class, 'Expectation value is not iterable.');

it('expects on each item', function () {
    expect([1, 1, 1])
        ->each()
        ->toEqual(1);

    expect(static::getCount())->toBe(3); // + 1 assertion

    expect([1, 1, 1])
        ->each
        ->toEqual(1);

    expect(static::getCount())->toBe(7);
});

it('chains expectations on each item', function () {
    expect([1, 1, 1])
        ->each()
        ->toBeInt()
        ->toEqual(1);

    expect(static::getCount())->toBe(6); // + 1 assertion

    expect([2, 2, 2])
        ->each
        ->toBeInt
        ->toEqual(2);

    expect(static::getCount())->toBe(13);
});

test('opposite expectations on each item', function () {
    expect([1, 2, 3])
        ->each()
        ->not()
        ->toEqual(4);

    expect(static::getCount())->toBe(3);

    expect([1, 2, 3])
        ->each()
        ->not->toBeString;

    expect(static::getCount())->toBe(7);
});

test('chained opposite and non-opposite expectations', function () {
    expect([1, 2, 3])
        ->each()
        ->not()
        ->toEqual(4)
        ->toBeInt();

    expect(static::getCount())->toBe(6);
});

it('can add expectations via "and"', function () {
    expect([1, 2, 3])
        ->each()
        ->toBeInt // + 3
        ->and([4, 5, 6])
        ->each
        ->toBeLessThan(7) // + 3
        ->not
        ->toBeLessThan(3)
        ->toBeGreaterThan(3) // + 3
        ->and('Hello World')
        ->toBeString // + 1
        ->toEqual('Hello World'); // + 1

    expect(static::getCount())->toBe(14);
});

it('accepts callables', function () {
    expect([1, 2, 3])->each(function ($number) {
        expect($number)->toBeInstanceOf(Expectation::class);
        expect($number->value)->toBeInt();
        $number->toBeInt->not->toBeString;
    });

    expect(static::getCount())->toBe(12);
});

it('passes the key of the current item to callables', function () {
    expect([1, 2, 3])->each(function ($number, $key) {
        expect($key)->toBeInt();
    });

    expect(static::getCount())->toBe(3);
});
