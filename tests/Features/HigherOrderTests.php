<?php

use PHPUnit\Framework\TestCase;

beforeEach()->assertTrue(true);

it('proxies calls to object')->assertTrue(true);

it('is capable doing multiple assertions')
    ->assertTrue(true)
    ->assertFalse(false);

it('resolves expect callables correctly')
    ->expect(function () { return 'foo'; })
    ->toBeString()
    ->toBe('foo')
    ->and('bar')
    ->toBeString()
    ->toBe('bar');

it('can tap into the test')
    ->expect('foo')->toBeString()
    ->tap(function () { expect($this)->toBeInstanceOf(TestCase::class); })
    ->toBe('foo')
    ->and('hello world')->toBeString();

afterEach()->assertTrue(true);
