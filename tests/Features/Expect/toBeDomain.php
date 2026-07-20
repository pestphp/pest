<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('example.com')->toBeDomain() // standard domain
        ->and('sub.example.com')->toBeDomain() // subdomain
        ->and('my-host.io')->toBeDomain() // with hyphen
        ->and('example.co.uk')->toBeDomain(); // multi-level TLD
});

test('failures', function (): void {
    expect('example')->toBeDomain();
})->throws(ExpectationFailedException::class);

test('failures with leading dot', function (): void {
    expect('.example.com')->toBeDomain();
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
