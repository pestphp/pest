<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('passes with int', function (): void {
    expect(0)->toBeZero();
});

test('passes with float', function (): void {
    expect(0.0)->toBeZero();
});

test('passes with numeric string', function (): void {
    expect('0')->toBeZero();
});

test('failure with positive', function (): void {
    expect(1)->toBeZero();
})->throws(ExpectationFailedException::class);

test('failure with negative', function (): void {
    expect(-1)->toBeZero();
})->throws(ExpectationFailedException::class);

test('failure with custom message', function (): void {
    expect(1)->toBeZero('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not', function (): void {
    expect(1)->not->toBeZero();
});

test('not failure', function (): void {
    expect(0)->not->toBeZero();
})->throws(ExpectationFailedException::class);
