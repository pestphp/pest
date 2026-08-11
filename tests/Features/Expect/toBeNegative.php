<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('passes with int', function (): void {
    expect(-1)->toBeNegative();
});

test('passes with float', function (): void {
    expect(-0.5)->toBeNegative();
});

test('failure with zero', function (): void {
    expect(0)->toBeNegative();
})->throws(ExpectationFailedException::class);

test('failure with positive', function (): void {
    expect(1)->toBeNegative();
})->throws(ExpectationFailedException::class);

test('failure with custom message', function (): void {
    expect(1)->toBeNegative('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not', function (): void {
    expect(1)->not->toBeNegative();
});

test('not failure', function (): void {
    expect(-1)->not->toBeNegative();
})->throws(ExpectationFailedException::class);
