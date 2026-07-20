<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect([1, 2, 2])->toHaveDuplicates()
        ->and(['a', 'b', 'a'])->toHaveDuplicates()
        ->and([[1], [1]])->toHaveDuplicates()
        ->and([1, 2, 3])->not->toHaveDuplicates()
        ->and([])->not->toHaveDuplicates()
        ->and([1])->not->toHaveDuplicates()
        ->and([[1], [2]])->not->toHaveDuplicates();
});

test('failures', function (): void {
    expect([1, 2, 3])->toHaveDuplicates();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect([1, 2, 3])->toHaveDuplicates('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with invalid type', function (): void {
    expect('foo')->toHaveDuplicates();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [array].');

test('not failures', function (): void {
    expect([1, 2, 2])->not->toHaveDuplicates();
})->throws(ExpectationFailedException::class);
