<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('Zm9v')->toBeBase64() // 'foo'
        ->and('')->toBeBase64()
        ->and('dGVzdA==')->toBeBase64(); // 'test'
});

test('failures', function (): void {
    expect('not-base64!')->toBeBase64();
})->throws(ExpectationFailedException::class);

test('failures with invalid type', function (): void {
    expect([])->toBeBase64();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('failures with custom message', function (): void {
    expect('!!invalid!!')->toBeBase64('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('Zm9v')->not->toBeBase64();
})->throws(ExpectationFailedException::class);
