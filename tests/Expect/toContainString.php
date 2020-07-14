<?php

use PHPUnit\Framework\ExpectationFailedException;

test('is case sensitive', function () {
    expect('hello world')->toContainString('world');
    expect('hello world')->not->toContainString('World');
});

test('failures', function () {
    expect('hello world')->toContainString('Hello');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('hello world')->not->toContainString('hello');
})->throws(ExpectationFailedException::class);
