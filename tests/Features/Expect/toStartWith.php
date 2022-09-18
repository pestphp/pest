<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('username')->toStartWith('user');
});

test('failures', function () {
    expect('username')->toStartWith('password');
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('username')->toStartWith('password', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('username')->not->toStartWith('user');
})->throws(ExpectationFailedException::class);
