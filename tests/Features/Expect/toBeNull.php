<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(null)->toBeNull();
    expect('')->not->toBeNull();
});

test('failures', function () {
    expect('hello')->toBeNull();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('hello')->toBeNull('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(null)->not->toBeNull();
})->throws(ExpectationFailedException::class);
