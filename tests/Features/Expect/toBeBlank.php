<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('')->toBeBlank()
        ->and('   ')->toBeBlank()
        ->and("\n\t")->toBeBlank()
        ->and('hello')->not->toBeBlank()
        ->and(' hello ')->not->toBeBlank();
});

test('failures', function (): void {
    expect('hello')->toBeBlank();
})->throws(ExpectationFailedException::class);

test('failures with invalid type', function (): void {
    expect([])->toBeBlank();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('failures with custom message', function (): void {
    expect('hello')->toBeBlank('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('')->not->toBeBlank();
})->throws(ExpectationFailedException::class);
