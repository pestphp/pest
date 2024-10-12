<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(new class {})->toBeAnonymous();
});

test('failure with null', function () {
    expect(null)->toBeAnonymous();
})->throws(ExpectationFailedException::class);

test('failure with blank', function () {
    expect('')->toBeAnonymous();
})->throws(ExpectationFailedException::class);

test('failure with boolean', function () {
    expect(true)->toBeAnonymous();
})->throws(ExpectationFailedException::class);

test('failure with zero', function () {
    expect(0)->toBeAnonymous();
})->throws(ExpectationFailedException::class);

test('failure with integer', function () {
    expect(1024)->toBeAnonymous();
})->throws(ExpectationFailedException::class);

test('failure with float', function () {
    expect(-1.80)->toBeAnonymous();
})->throws(ExpectationFailedException::class);

test('failure with array', function () {
    expect(['foo'])->toBeAnonymous();
})->throws(ExpectationFailedException::class);

test('failure with stdClass', function () {
    expect(new stdClass)->toBeAnonymous();
})->throws(ExpectationFailedException::class);

test('failure with function', function () {
    expect(fn () => new class () {})->toBeAnonymous();
})->throws(ExpectationFailedException::class);

test('failure with non anonymous class', function () {
    expect(new Foo)->toBeAnonymous();
})->throws(ExpectationFailedException::class);

class Foo {}
