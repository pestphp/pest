<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect([1, 2, 3])->toBeArray();
    expect('1, 2, 3')->not->toBeArray();
});

test('failures', function () {
    expect(null)->toBeArray();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(null)->toBeArray('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(['a', 'b', 'c'])->not->toBeArray();
})->throws(ExpectationFailedException::class);
