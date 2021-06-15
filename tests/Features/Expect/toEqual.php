<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('00123')->toEqual(123);
});

test('failures', function () {
    expect(['a', 'b', 'c'])->toEqual(['a', 'b']);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('042')->not->toEqual(42);
})->throws(ExpectationFailedException::class);
