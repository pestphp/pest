<?php

use PHPUnit\Framework\TestCase;

beforeEach()->assertTrue(true);

it('proxies calls to object')->assertTrue(true);

it('is capable doing multiple assertions')
    ->assertTrue(true)
    ->assertFalse(false);

it('can tap into the test')
    ->expect('foo')->toBeString()->toBe('foo')
    ->tap(function () { expect($this)->toBeInstanceOf(TestCase::class); })
    ->and('hello world')->toBeString();

it('can use the returned instance from a tap')
    ->expect('foo')->toBeString()->toBe('foo')
    ->tap(function () { return expect($this); })
    ->toBeInstanceOf(TestCase::class);

afterEach()->assertTrue(true);
