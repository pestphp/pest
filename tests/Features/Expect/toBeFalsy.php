<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes as falsy', function ($value) {
    expect($value)->toBeFalsy();
})->with([false, '', null, 0, '0']);

test('passes as not falsy', function ($value) {
    expect($value)->not->toBeFalsy();
})->with([true, [1], 'false', 1, -1]);

test('failures', function () {
    expect(1)->toBeFalsy();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(1)->toBeFalsy('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(null)->not->toBeFalsy();
})->throws(ExpectationFailedException::class);
