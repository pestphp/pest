<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes strings', function () {
    expect('Nuno')->toContainAtMost(1, 'Nu');
});

test('passes arrays', function () {
    expect([1, 42, 2, 42, 31, 42])->toContainAtMost(3, 42);
});

test('passes with array needles', function () {
    expect([[1, 2, 3], 2, 42, [1, 2, 3]])->toContainAtMost(2, [1, 2, 3]);
});

test('not success', function () {
    expect([1, 2, 42, 2, 97, 31, 2])->not->toContainAtMost(1, 2);
});

test('passes when exact', function () {
    expect([1, 2, 42, 2, 2])->toContainAtMost(3, 2);
});

test('failures', function () {
    expect([1, 2, 42, 2])->toContainAtMost(1, 2);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([1, 42, 97, 31, 2])->not->toContainAtMost(2, 2);
})->throws(ExpectationFailedException::class);

test('failures when count is zero', function () {
    expect([1, 2, 42, 2, 2])->toContainAtMost(0, 3);
})->throws(ExpectationFailedException::class);

test('failures when count is negative', function () {
    expect([1, 2, 42, 2, 2])->toContainAtMost(-1, 2);
})->throws(ExpectationFailedException::class);
