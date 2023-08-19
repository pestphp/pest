<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('Abc')->toBeStudlyCase();
    expect('AbcDef')->toBeStudlyCase();
    expect('abc-def')->not->toBeStudlyCase();
    expect('abc-def')->not->toBeStudlyCase();
    expect('abc')->not->toBeStudlyCase();
});

test('failures', function () {
    expect('abc')->toBeStudlyCase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('abc')->toBeStudlyCase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('AbcDef')->not->toBeStudlyCase();
})->throws(ExpectationFailedException::class);
