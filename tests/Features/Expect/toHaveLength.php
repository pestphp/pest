<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

it('passes', function ($value): void {
    expect($value)->toHaveLength(9);
})->with([
    'Fortaleza',
    'Sollefteå',
    'Ιεράπετρα',
    (object) [1, 2, 3, 4, 5, 6, 7, 8, 9],
]);

it('passes with array', function (): void {
    expect([1, 2, 3])->toHaveLength(3);
});

it('passes with *not*', function (): void {
    expect('')->not->toHaveLength(1);
});

it('properly fails with *not*', function (): void {
    expect('pest')->not->toHaveLength(4, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

it('fails', function (): void {
    expect([1, 1.5, true, null])->toHaveLength(1);
})->throws(ExpectationFailedException::class);

it('fails with message', function (): void {
    expect([1, 1.5, true, null])->toHaveLength(1, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');
