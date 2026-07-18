<?php

declare(strict_types=1);

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

it('does not truncate long string arguments in error message', function (): void {
    $expectation = new OppositeExpectation(expect('foo'));

    $longMessage = 'Very long error message. Very long error message. Very long error message.';

    $expectation->throwExpectationFailedException('toBe', [$longMessage]);
})->throws(ExpectationFailedException::class, 'Very long error message. Very long error message. Very long error message.');

it('does not truncate custom error message when using not()', function (): void {
    $longMessage = 'This is a very detailed custom error message that should not be truncated in the output.';

    expect(true)->not()->toBeTrue($longMessage);
})->throws(ExpectationFailedException::class, 'This is a very detailed custom error message that should not be truncated in the output.');
