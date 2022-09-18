<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(42)->toBeInt();
    expect(42.0)->not->toBeInt();
});

test('failures', function () {
    expect(42.0)->toBeInt();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(42.0)->toBeInt('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(6 * 7)->not->toBeInt();
})->throws(ExpectationFailedException::class);
