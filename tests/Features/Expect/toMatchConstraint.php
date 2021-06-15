<?php

use PHPUnit\Framework\Constraint\IsTrue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(true)->toMatchConstraint(new IsTrue());
});

test('failures', function () {
    expect(false)->toMatchConstraint(new IsTrue());
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(true)->not->toMatchConstraint(new IsTrue());
})->throws(ExpectationFailedException::class);
