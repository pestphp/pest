<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect([1, 2, 3])->toHaveUniqueItems()
        ->and(['a', 'b', 'c'])->toHaveUniqueItems()
        ->and([])->toHaveUniqueItems()
        ->and([1])->toHaveUniqueItems()
        ->and([1, 2, 2])->not->toHaveUniqueItems()
        ->and(['a', 'a'])->not->toHaveUniqueItems();
});

test('failures', function (): void {
    expect([1, 2, 2])->toHaveUniqueItems();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect([1, 2, 2])->toHaveUniqueItems('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect([1, 2, 3])->not->toHaveUniqueItems();
})->throws(ExpectationFailedException::class);
