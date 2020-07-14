<?php

use PHPUnit\Framework\ExpectationFailedException;

test('ignore difference in casing', function () {
    expect('hello world')->toContainStringIgnoringCase('world');
    expect('hello world')->toContainStringIgnoringCase('World');
});

test('failures', function () {
    expect('hello world')->toContainStringIgnoringCase('hi');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('hello world')->not->toContainStringIgnoringCase('Hello');
    expect('hello world')->not->toContainStringIgnoringCase('hello');
})->throws(ExpectationFailedException::class);
