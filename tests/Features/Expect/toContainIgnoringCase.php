<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('Hello World')->toContainIgnoringCase('world')
        ->and('PestPHP')->toContainIgnoringCase('php')
        ->and('PestPHP')->toContainIgnoringCase('PESTPHP')
        ->and('Laravel')->not->toContainIgnoringCase('symfony');
});

test('failures', function (): void {
    expect('Hello World')->toContainIgnoringCase('Symfony');
})->throws(ExpectationFailedException::class);

test('failures with invalid type', function (): void {
    expect([])->toContainIgnoringCase('world');
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('failures with custom message', function (): void {
    expect('Hello World')->toContainIgnoringCase('Symfony', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('PestPHP')->not->toContainIgnoringCase('php');
})->throws(ExpectationFailedException::class);
