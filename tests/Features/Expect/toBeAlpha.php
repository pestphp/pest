<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('abc')->toBeAlpha();
    expect('123')->not->toBeAlpha();
});

test('failures', function () {
    expect('123')->toBeAlpha();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('123')->toBeAlpha('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('abc')->not->toBeAlpha();
})->throws(ExpectationFailedException::class);
