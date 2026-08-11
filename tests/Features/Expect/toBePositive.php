<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('passes with int', function (): void {
    expect(1)->toBePositive();
});

test('passes with float', function (): void {
    expect(0.5)->toBePositive();
});

test('failure with zero', function (): void {
    expect(0)->toBePositive();
})->throws(ExpectationFailedException::class);

test('failure with negative', function (): void {
    expect(-1)->toBePositive();
})->throws(ExpectationFailedException::class);

test('failure with custom message', function (): void {
    expect(-1)->toBePositive('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not', function (): void {
    expect(-1)->not->toBePositive();
});

test('not failure', function (): void {
    expect(1)->not->toBePositive();
})->throws(ExpectationFailedException::class);
