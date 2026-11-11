<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('passes as falsy', function ($value): void {
    expect($value)->toBeFalsy();
})->with([false, '', null, 0, '0']);

test('passes as not falsy', function ($value): void {
    expect($value)->not->toBeFalsy();
})->with([true, [1], 'false', 1, -1]);

test('failures', function (): void {
    expect(1)->toBeFalsy();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(1)->toBeFalsy('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect()->not->toBeFalsy();
})->throws(ExpectationFailedException::class);
