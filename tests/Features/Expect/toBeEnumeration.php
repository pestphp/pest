<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(1)->toBeEnumeration('Tests\\Fixtures\\Features\\BackedIntEnumeration');
    expect(2)->toBeEnumeration('Tests\\Fixtures\\Features\\BackedIntEnumeration');
    expect('foo')->toBeEnumeration('Tests\\Fixtures\\Features\\BackedStringEnumeration');
    expect('bar')->toBeEnumeration('Tests\\Fixtures\\Features\\BackedStringEnumeration');
});

test('failures', function () {
    expect('baz')->toBeEnumeration('Tests\\Fixtures\\Features\\BackedStringEnumeration');
    expect('bar')->toBeEnumeration('Tests\\Fixtures\\Features\\BackedStringEnumeration');
    expect(3)->toBeEnumeration('Tests\\Fixtures\\Features\\BackedIntEnumeration');
    expect(4)->toBeEnumeration('Tests\\Fixtures\\Features\\BackedIntEnumeration');
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('baz')->toBeEnumeration('Tests\\Fixtures\\Features\\BackedStringEnumeration', 'oh no!');
    expect(3)->toBeEnumeration('Tests\\Fixtures\\Features\\BackedIntEnumeration');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(1)->not->toBeEnumeration('Tests\\Fixtures\\Features\\BackedIntEnumeration');
    expect('bar')->not->toBeEnumeration('Tests\\Fixtures\\Features\\BackedStringEnumeration');
})->throws(ExpectationFailedException::class);
