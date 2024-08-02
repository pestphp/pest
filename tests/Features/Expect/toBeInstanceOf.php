<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(new Exception)->toBeInstanceOf(Exception::class);
    expect(new Exception)->not->toBeInstanceOf(RuntimeException::class);
});

test('failures', function () {
    expect(new Exception)->toBeInstanceOf(RuntimeException::class);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(new Exception)->toBeInstanceOf(RuntimeException::class, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(new Exception)->not->toBeInstanceOf(Exception::class);
})->throws(ExpectationFailedException::class);
