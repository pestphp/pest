<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('Hello World')->toMatch('/^hello wo.*$/i');
});

test('failures', function () {
    expect('Hello World')->toMatch('/^hello$/i');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('Hello World')->not->toMatch('/^hello wo.*$/i');
})->throws(ExpectationFailedException::class);
