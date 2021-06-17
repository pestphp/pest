<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('username')->toStartWith('user');
});

test('failures', function () {
    expect('username')->toStartWith('password');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('username')->not->toStartWith('user');
})->throws(ExpectationFailedException::class);
