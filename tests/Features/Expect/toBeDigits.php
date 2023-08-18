<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('123')->toBeDigits();
    expect('123.14')->not->toBeDigits();
});

test('failures', function () {
    expect('123.14')->toBeDigits();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('123.14')->toBeDigits('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('445')->not->toBeDigits();
})->throws(ExpectationFailedException::class);
