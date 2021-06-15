<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect([])->toBeEmpty();
    expect(null)->toBeEmpty();
});

test('failures', function () {
    expect([1, 2])->toBeEmpty();
    expect(' ')->toBeEmpty();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([])->not->toBeEmpty();
    expect(null)->not->toBeEmpty();
})->throws(ExpectationFailedException::class);
