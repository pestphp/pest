<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('abc')->toBeCamelCase();
    expect('abcDef')->toBeCamelCase();
    expect('abc-def')->not->toBeCamelCase();
    expect('abc-def')->not->toBeCamelCase();
    expect('AbcDef')->not->toBeCamelCase();
});

test('failures', function () {
    expect('Abc')->toBeCamelCase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('Abc')->toBeCamelCase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('abcDef')->not->toBeCamelCase();
})->throws(ExpectationFailedException::class);
