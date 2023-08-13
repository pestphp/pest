<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('UPPERCASE')->toBeUppercase();
    expect('lowercase')->not->toBeUppercase();
});

test('failures', function () {
    expect('lowercase')->toBeUppercase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('lowercase')->toBeUppercase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('UPPERCASE')->not->toBeUppercase();
})->throws(ExpectationFailedException::class);
