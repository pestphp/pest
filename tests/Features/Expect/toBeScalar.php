<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(1.1)->toBeScalar();
});

test('failures', function () {
    expect(null)->toBeScalar();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(null)->toBeScalar('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(42)->not->toBeScalar();
})->throws(ExpectationFailedException::class);
