<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('abcdef')->toBeHexadecimal()
        ->and('ABCDEF')->toBeHexadecimal()
        ->and('aBcDeF')->toBeHexadecimal()
        ->and('1234567890')->toBeHexadecimal()
        ->and('deadbeef')->toBeHexadecimal();
});

test('failures', function (): void {
    expect('xyz')->toBeHexadecimal();
})->throws(ExpectationFailedException::class);

test('failures with invalid characters', function (): void {
    expect('ghijkl')->toBeHexadecimal();
})->throws(ExpectationFailedException::class);

test('failures with zero-x prefix', function (): void {
    expect('0x1a')->toBeHexadecimal();
})->throws(ExpectationFailedException::class);

test('failures with empty string', function (): void {
    expect('')->toBeHexadecimal();
})->throws(ExpectationFailedException::class);

test('failures with invalid type', function (): void {
    expect([])->toBeHexadecimal();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('failures with custom message', function (): void {
    expect('xyz')->toBeHexadecimal('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('abcdef')->not->toBeHexadecimal();
})->throws(ExpectationFailedException::class);
