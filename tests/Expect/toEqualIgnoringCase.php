<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('hello')->toEqualIgnoringCase('HELLO');
});

test('failures', function () {
    expect('hello')->toEqualIgnoringCase('BAR');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('HELLO')->not->toEqualIgnoringCase('HelLo');
})->throws(ExpectationFailedException::class);
