<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('passes with positive even', function (): void {
    expect(4)->toBeEven();
});

test('passes with negative even', function (): void {
    expect(-8)->toBeEven();
});

test('passes with zero', function (): void {
    expect(0)->toBeEven();
});

test('failure with odd', function (): void {
    expect(3)->toBeEven();
})->throws(ExpectationFailedException::class);

test('failure with custom message', function (): void {
    expect(3)->toBeEven('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not', function (): void {
    expect(3)->not->toBeEven();
});

test('not failure', function (): void {
    expect(4)->not->toBeEven();
})->throws(ExpectationFailedException::class);
