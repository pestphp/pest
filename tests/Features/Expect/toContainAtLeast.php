<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes strings', function () {
    expect('Nuno')->toContainAtLeast(1, 'Nu');
});

test('passes arrays', function () {
    expect([1, 42, 2, 42, 31, 42])->toContainAtLeast(2, 42);
});

test('passes with array needles', function () {
    expect([[1, 2, 3], 2, 42, [1, 2, 3]])->toContainAtLeast(1, [1, 2, 3]);
});

test('not success', function () {
    expect([1, 2, 42, 2, 97, 31, 2])->not->toContainAtLeast(2, 31);
});

test('passes when exact', function () {
    expect([1, 2, 42, 2, 2])->toContainAtLeast(3, 2);
});

test('failures', function () {
    expect([1, 2, 42, 2])->toContainAtLeast(3, 2);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([1, 2, 42, 2, 97, 31, 2])->not->toContainAtLeast(2, 2);
})->throws(ExpectationFailedException::class);

test('failures when count is zero', function () {
    expect([1, 2, 42, 2, 2])->toContainAtLeast(0, 3);
})->throws(ExpectationFailedException::class);

test('failures when count is negative', function () {
    expect([1, 2, 42, 2, 2])->toContainAtLeast(-1, 2);
})->throws(ExpectationFailedException::class);
