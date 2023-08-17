<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('abc')->toBeSnakeCase();
    expect('abc_def')->toBeSnakeCase();
    expect('abc-def')->not->toBeSnakeCase();
    expect('abcDef')->not->toBeSnakeCase();
    expect('AbcDef')->not->toBeSnakeCase();
});

test('failures', function () {
    expect('Abc')->toBeSnakeCase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('Abc')->toBeSnakeCase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('abc_def')->not->toBeSnakeCase();
})->throws(ExpectationFailedException::class);
