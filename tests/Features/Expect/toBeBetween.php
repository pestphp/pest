<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('passes with int', function (): void {
    expect(2)->toBeBetween(1, 3);
});

test('passes with float', function (): void {
    expect(1.5)->toBeBetween(1.25, 1.75);
});

test('passes with float and int', function (): void {
    expect(1.5)->toBeBetween(1, 2);
});

test('passes with DateTime', function (): void {
    expect(new DateTime('2023-09-22'))->toBeBetween(new DateTime('2023-09-21'), new DateTime('2023-09-23'));
});

test('failure with int', function (): void {
    expect(4)->toBeBetween(1, 3);
})->throws(ExpectationFailedException::class);

test('failure with float', function (): void {
    expect(2)->toBeBetween(1.5, 1.75);
})->throws(ExpectationFailedException::class);

test('failure with float and int', function (): void {
    expect(2.1)->toBeBetween(1, 2);
})->throws(ExpectationFailedException::class);

test('failure with DateTime', function (): void {
    expect(new DateTime('2023-09-20'))->toBeBetween(new DateTime('2023-09-21'), new DateTime('2023-09-23'));
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(4)->toBeBetween(1, 3, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(2)->not->toBeBetween(1, 3);
})->throws(ExpectationFailedException::class);
