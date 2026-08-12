<?php

use Pest\Expectation;

test('an exception is thrown if the the type is not iterable', function (): void {
    expect('Foobar')->each()->toEqual('Foobar');
})->throws(BadMethodCallException::class, 'Expectation value is not iterable.');

it('expects on each item', function (): void {
    expect([1, 1, 1])
        ->each()
        ->toEqual(1)
        ->and(static::getCount())->toBe(3)
        ->and([1, 1, 1])->each->toEqual(1)
        ->and(static::getCount())->toBe(7);
});

it('chains expectations on each item', function (): void {
    expect([1, 1, 1])
        ->each()
        ->toBeInt()
        ->toEqual(1)
        ->and(static::getCount())->toBe(6)->and([2, 2, 2])->each->toBeInt->toEqual(2)
        ->and(static::getCount())->toBe(13);
});

test('opposite expectations on each item', function (): void {
    expect([1, 2, 3])
        ->each()
        ->not()
        ->toEqual(4)
        ->and(static::getCount())->toBe(3);

    expect([1, 2, 3])
        ->each()
        ->not->toBeString;

    expect(static::getCount())->toBe(7);
});

test('chained opposite and non-opposite expectations', function (): void {
    expect([1, 2, 3])
        ->each()
        ->not()
        ->toEqual(4)
        ->toBeInt()
        ->and(static::getCount())->toBe(6);
});

it('can add expectations via "and"', function (): void {
    expect([1, 2, 3])
        ->each()
        ->toBeInt
        ->and([4, 5, 6])
        ->each
        ->toBeLessThan(7)
        ->not
        ->toBeLessThan(3)
        ->toBeGreaterThan(3)
        ->and('Hello World')
        ->toBeString
        ->toEqual('Hello World')
        ->and(static::getCount())->toBe(14);
});

it('accepts callables', function (): void {
    expect([1, 2, 3])->each(function ($number): void {
        expect($number)->toBeInstanceOf(Expectation::class)
            ->and($number->value)->toBeInt();
        $number->toBeInt->not->toBeString;
    })
        ->and(static::getCount())->toBe(12);
});

it('passes the key of the current item to callables', function (): void {
    expect([1, 2, 3])->each(function ($number, $key): void {
        expect($key)->toBeInt();
    })
        ->and(static::getCount())->toBe(3);
});
