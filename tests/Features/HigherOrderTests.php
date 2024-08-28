<?php

use PHPUnit\Framework\TestCase;

beforeEach()->assertTrue(true);

it('proxies calls to object')->assertTrue(true);

it('is capable doing multiple assertions')
    ->assertTrue(true)
    ->assertFalse(false);

it('resolves expect callables correctly')
    ->expect(function () {
        return 'foo';
    })
    ->toBeString()
    ->toBe('foo')
    ->and('bar')
    ->toBeString()
    ->toBe('bar');

test('does not treat method names as callables')
    ->expect('it')->toBeString();

it('can defer a method until after test setup')
    ->expect('foo')->toBeString()
    ->defer(function () {
        expect($this)->toBeInstanceOf(TestCase::class);
    })
    ->toBe('foo')
    ->and('hello world')->toBeString();

it('can pass datasets into the expect callables')
    ->with([[1, 2, 3]])
    ->expect(function (...$numbers) {
        return $numbers;
    })->toBe([1, 2, 3])
    ->and(function (...$numbers) {
        return $numbers;
    })->toBe([1, 2, 3]);

it('can pass datasets into the defer callable')
    ->with([[1, 2, 3]])
    ->defer(function (...$numbers) {
        expect($numbers)->toBe([1, 2, 3]);
    });

it('can pass shared datasets into callables')
    ->with('numbers.closure.wrapped')
    ->expect(function ($value) {
        return $value;
    })
    ->and(function ($value) {
        return $value;
    })
    ->defer(function ($value) {
        expect($value)->toBeInt();
    })
    ->toBeInt();

afterEach()->assertTrue(true);
