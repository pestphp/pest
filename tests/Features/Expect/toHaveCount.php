<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect([1, 2, 3])->toHaveCount(3);
});

test('failures', function () {
    expect([1, 2, 3])->toHaveCount(4);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([1, 2, 3])->not->toHaveCount(3);
})->throws(ExpectationFailedException::class);
