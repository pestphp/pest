<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes as truthy', function ($value) {
    expect($value)->toBeTruthy();
})->with([true, [1], 'false', 1, -1]);

test('passes as not truthy', function ($value) {
    expect($value)->not->toBeTruthy();
})->with([false, '', null, 0, '0']);

test('failures', function () {
    expect(null)->toBeTruthy();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(null)->toBeTruthy('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(1)->not->toBeTruthy();
})->throws(ExpectationFailedException::class);
