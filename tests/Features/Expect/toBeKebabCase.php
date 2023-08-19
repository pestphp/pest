<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('abc')->toBeKebabCase();
    expect('abc-def')->toBeKebabCase();
    expect('abc_def')->not->toBeKebabCase();
    expect('abcDef')->not->toBeKebabCase();
    expect('AbcDef')->not->toBeKebabCase();
});

test('failures', function () {
    expect('Abc')->toBeKebabCase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('Abc')->toBeKebabCase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('abc-def')->not->toBeKebabCase();
})->throws(ExpectationFailedException::class);
