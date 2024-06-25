<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(function () {})->toBeCallable();
    expect(null)->not->toBeCallable();
});

test('failures', function () {
    $hello = 5;

    expect($hello)->toBeCallable();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    $hello = 5;

    expect($hello)->toBeCallable('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(function () {
        return 42;
    })->not->toBeCallable();
})->throws(ExpectationFailedException::class);
