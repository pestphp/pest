<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect([1, 2, 3])->toBeList();
    expect(['a' => 1, 'b' => 2, 'c' => 3])->not->toBeList();
    expect('1, 2, 3')->not->toBeList();
});

test('failures', function () {
    expect(null)->toBeList();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(null)->toBeList('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(['a', 'b', 'c'])->not->toBeList();
})->throws(ExpectationFailedException::class);
