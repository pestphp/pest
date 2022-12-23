<?php

use Pest\Expectations\OppositeExpectation;
use PHPUnit\Framework\ExpectationFailedException;

it('throw expectation failed exception with string argument', function (): void {
    $expectation = new OppositeExpectation(expect('foo'));

    $expectation->throwExpectationFailedException('toBe', 'bar');
})->throws(ExpectationFailedException::class, "Expecting 'foo' not to be 'bar'.");

it('throw expectation failed exception with array argument', function (): void {
    $expectation = new OppositeExpectation(expect('foo'));

    $expectation->throwExpectationFailedException('toBe', ['bar']);
})->throws(ExpectationFailedException::class, "Expecting 'foo' not to be 'bar'.");
