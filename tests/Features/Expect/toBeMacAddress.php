<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('00:1a:2b:3c:4d:5e')->toBeMacAddress() // colon-separated
        ->and('00-1a-2b-3c-4d-5e')->toBeMacAddress() // hyphen-separated
        ->and('ff:ff:ff:ff:ff:ff')->toBeMacAddress();
});

test('failures', function (): void {
    expect('not-a-mac')->toBeMacAddress();
})->throws(ExpectationFailedException::class);

test('failures with invalid type', function (): void {
    expect([])->toBeMacAddress();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('failures with custom message', function (): void {
    expect('not-a-mac')->toBeMacAddress('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('00:1a:2b:3c:4d:5e')->not->toBeMacAddress();
})->throws(ExpectationFailedException::class);
