<?php

use PHPUnit\Framework\ExpectationFailedException;

test('strict comparisons', function () {
    expect(true)->toBeTrue();
});

test('failures', function () {
    expect('')->toBeTrue();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('')->toBeTrue('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(false)->not->toBe(false);
})->throws(ExpectationFailedException::class);
