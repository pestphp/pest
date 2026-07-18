<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('passes as truthy', function ($value): void {
    expect($value)->toBeTruthy();
})->with([true, [1], 'false', 1, -1]);

test('passes as not truthy', function ($value): void {
    expect($value)->not->toBeTruthy();
})->with([false, '', null, 0, '0']);

test('failures', function (): void {
    expect()->toBeTruthy();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect()->toBeTruthy('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(1)->not->toBeTruthy();
})->throws(ExpectationFailedException::class);
