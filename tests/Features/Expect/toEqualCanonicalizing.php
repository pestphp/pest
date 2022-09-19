<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect([1, 2, 3])->toEqualCanonicalizing([3, 1, 2]);
    expect(['g', 'a', 'z'])->not->toEqualCanonicalizing(['a', 'z']);
});

test('failures', function () {
    expect([3, 2, 1])->toEqualCanonicalizing([1, 2]);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect([3, 2, 1])->toEqualCanonicalizing([1, 2], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(['a', 'b', 'c'])->not->toEqualCanonicalizing(['b', 'a', 'c']);
})->throws(ExpectationFailedException::class);
