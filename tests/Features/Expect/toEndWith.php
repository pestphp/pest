<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('username')->toEndWith('name');
});

test('failures', function () {
    expect('username')->toEndWith('password');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('username')->not->toEndWith('name');
})->throws(ExpectationFailedException::class);
