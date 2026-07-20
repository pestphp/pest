<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('example.com')->toBeDomain()
        ->and('sub.example.com')->toBeDomain()
        ->and('my-host.io')->toBeDomain();
});

test('failures', function (): void {
    expect('example')->toBeDomain();
})->throws(ExpectationFailedException::class);

test('failures with invalid type', function (): void {
    expect([])->toBeDomain();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('failures with custom message', function (): void {
    expect('example')->toBeDomain('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('example.com')->not->toBeDomain();
})->throws(ExpectationFailedException::class);
