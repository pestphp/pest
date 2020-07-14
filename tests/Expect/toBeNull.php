<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(null)->toBeNull();
    expect('')->not->toBeNull();
});

test('failures', function () {
    expect('hello')->toBeNull();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(null)->not->toBeNull();
})->throws(ExpectationFailedException::class);
