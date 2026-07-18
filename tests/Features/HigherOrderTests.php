<?php

use PHPUnit\Framework\TestCase;

beforeEach()->assertTrue(true);

it('proxies calls to object')->assertTrue(true);

it('is capable doing multiple assertions')
    ->assertTrue(true)
    ->assertFalse(false);

it('resolves expect callables correctly')
    ->expect(fn (): string => 'foo')
    ->toBeString()
    ->toBe('foo')
    ->and('bar')
    ->toBeString()
    ->toBe('bar');

test('does not treat method names as callables')
    ->expect('it')->toBeString();

it('can defer a method until after test setup')
    ->expect('foo')->toBeString()
    ->defer(function (): void {
        expect($this)->toBeInstanceOf(TestCase::class);
    })
    ->toBe('foo')
    ->and('hello world')->toBeString();

it('can pass datasets into the expect callables')
    ->with([[1, 2, 3]])
    ->expect(fn (...$numbers): array => $numbers)->toBe([1, 2, 3])
    ->and(fn (...$numbers): array => $numbers)->toBe([1, 2, 3]);

it('can pass datasets into the defer callable')
    ->with([[1, 2, 3]])
    ->defer(function (...$numbers): void {
        expect($numbers)->toBe([1, 2, 3]);
    });

it('can pass shared datasets into callables')
    ->with('numbers.closure.wrapped')
    ->expect(fn ($value) => $value)
    ->and(fn ($value) => $value)
    ->defer(function ($value): void {
        expect($value)->toBeInt();
    })
    ->toBeInt();

afterEach()->assertTrue(true);
