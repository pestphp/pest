<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect([])->toBeDeepOf(0);
    expect([1, 2, 3])->toBeDeepOf(0);
    expect([1, 2 => [1, 2], 3 => [1]])->toBeDeepOf(1);
    expect([1, 2 => [1, 2], 3 => [1 => [1]]])->toBeDeepOf(2);
    expect('1, 2, 3')->not->toBeDeepOf(1);
});

test('failures', function () {
    expect([1, 2, 3])->toBeDeepOf(1);
})->throws(ExpectationFailedException::class);

test('failures when not array passed', function () {
    expect('not array')->toBeDeepOf(1);
})->throws(ExpectationFailedException::class);

test('failures when depth is negative', function () {
    expect([])->toBeDeepOf(-1);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect([1, 2, 3])->toBeDeepOf(1, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect([1, 2, 3])->not->toBeDeepOf(0);
})->throws(ExpectationFailedException::class);
