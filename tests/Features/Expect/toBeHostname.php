<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('example')->toBeHostname()
        ->and('example.com')->toBeHostname()
        ->and('my-host')->toBeHostname();
});

test('failures', function (): void {
    expect('-example')->toBeHostname();
})->throws(ExpectationFailedException::class);

test('failures with invalid type', function (): void {
    expect([])->toBeHostname();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('failures with custom message', function (): void {
    expect('-example')->toBeHostname('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('example.com')->not->toBeHostname();
})->throws(ExpectationFailedException::class);
