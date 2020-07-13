<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('00123')->toEqualCanonicalizing(123);
    expect(['a', 'b', 'c'])->toEqualCanonicalizing(['c', 'a', 'b']);
});

test('failures', function () {
    expect(['a', 'b', 'c'])->toEqualCanonicalizing(['a', 'b']);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('042')->not->toEqualCanonicalizing(42);
})->throws(ExpectationFailedException::class);
