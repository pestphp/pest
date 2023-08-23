<?php

use PHPUnit\Framework\ExpectationFailedException;
use Tests\Fixtures\Features\BackedIntEnumeration;
use Tests\Fixtures\Features\BackedStringEnumeration;

test('pass', function () {
    expect(1)->toBeEnumeration(BackedIntEnumeration::class);
    expect(2)->toBeEnumeration(BackedIntEnumeration::class);
    expect('foo')->toBeEnumeration(BackedStringEnumeration::class);
    expect('bar')->toBeEnumeration(BackedStringEnumeration::class);
});

test('failures', function () {
    expect('baz')->toBeEnumeration(BackedStringEnumeration::class);
    expect('bar')->toBeEnumeration(BackedStringEnumeration::class);
    expect(3)->toBeEnumeration(BackedIntEnumeration::class);
    expect(4)->toBeEnumeration(BackedIntEnumeration::class);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('baz')->toBeEnumeration(BackedStringEnumeration::class, 'oh no!');
    expect(3)->toBeEnumeration(BackedIntEnumeration::class, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(1)->not->toBeEnumeration(BackedIntEnumeration::class);
    expect('bar')->not->toBeEnumeration(BackedStringEnumeration::class);
})->throws(ExpectationFailedException::class);
