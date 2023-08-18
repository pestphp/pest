<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('abc123')->toBeAlphaNumeric();
    expect('-')->not->toBeAlphaNumeric();
});

test('failures', function () {
    expect('-')->toBeAlphaNumeric();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('-')->toBeAlphaNumeric('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('abc123')->not->toBeAlphaNumeric();
})->throws(ExpectationFailedException::class);
