<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(collect([1, 2, 3]))->toBeCollection();
    expect('1, 2, 3')->not->toBeCollection();
});

test('failures', function () {
    expect((object) [])->toBeCollection();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(collect(['a', 'b', 'c']))->not->toBeCollection();
})->throws(ExpectationFailedException::class);
