<?php

use PHPUnit\Framework\Constraint\IsTrue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(true)->toMatchConstraint(new IsTrue);
});

test('failures', function (): void {
    expect(false)->toMatchConstraint(new IsTrue);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(false)->toMatchConstraint(new IsTrue, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(true)->not->toMatchConstraint(new IsTrue);
})->throws(ExpectationFailedException::class);
