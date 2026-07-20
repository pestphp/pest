<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(2)->toBeEven()
        ->and(10)->toBeEven()
        ->and(0)->toBeEven()
        ->and(-4)->toBeEven()
        ->and(3)->not->toBeEven()
        ->and(-5)->not->toBeEven();
});

test('failures', function (): void {
    expect(3)->toBeEven();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(3)->toBeEven('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with invalid type', function (): void {
    expect([])->toBeEven();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [int].');

test('not failures', function (): void {
    expect(10)->not->toBeEven();
})->throws(ExpectationFailedException::class);
