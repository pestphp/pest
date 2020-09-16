<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('Hello World')->toMatchRegEx('/^hello wo.*$/i');
});

test('failures', function () {
    expect('Hello World')->toMatchRegEx('/^hello$/i');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('Hello World')->not->toMatchRegEx('/^hello wo.*$/i');
})->throws(ExpectationFailedException::class);
