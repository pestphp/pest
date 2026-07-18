<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('failures with wrong type', function (): void {
    expect([])->toBeUlid();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('pass', function (): void {
    expect('01ARZ3NDEKTSV4RRFFQ69G5FAV')->toBeUlid()
        ->and('01BX5ZZKBKACTAV9WEVGEMMVRE')->toBeUlid()
        ->and('7ZZZZZZZZZ0000000000000000')->toBeUlid();
});

test('failures', function (): void {
    expect('foo')->toBeUlid();
})->throws(ExpectationFailedException::class);

test('failures with message', function (): void {
    expect('bar')->toBeUlid('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('foo')->not->toBeUlid();
});
