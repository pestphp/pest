<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect([1, 2, 3])->toCount(3);
});

test('failures', function () {
    expect([1, 2, 3])->toCount(4);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([1, 2, 3])->not->toCount(3);
})->throws(ExpectationFailedException::class);
