<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(42)->toBeNumeric();
    expect('A')->not->toBeNumeric();
});

test('failures', function () {
    expect(null)->toBeNumeric();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(6 * 7)->not->toBeNumeric();
})->throws(ExpectationFailedException::class);
