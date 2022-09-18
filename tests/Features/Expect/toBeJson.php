<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('{"hello":"world"}')->toBeJson();
    expect('foo')->not->toBeJson();
    expect('{"hello"')->not->toBeJson();
});

test('failures', function () {
    expect(':"world"}')->toBeJson();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(':"world"}')->toBeJson('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('{"hello":"world"}')->not->toBeJson();
})->throws(ExpectationFailedException::class);
