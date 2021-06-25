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

test('not failures', function () {
    expect('{"hello":"world"}')->not->toBeJson();
})->throws(ExpectationFailedException::class);
