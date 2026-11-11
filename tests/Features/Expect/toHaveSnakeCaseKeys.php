<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

$array = [
    'snake' => true,
    'snake_case' => [
        'snake' => true,
        'snake_case' => [
            'snake' => true,
            'snake_case' => true,
        ],
        'list' => [
            'abc',
            'def',
            'ghi',
        ],
    ],
];

test('pass', function () use ($array): void {
    expect($array)->toHaveSnakeCaseKeys();
});

test('failures', function (): void {
    expect('not-an-array')->toHaveSnakeCaseKeys();
})->throws(InvalidExpectationValue::class);

test('failures with message', function () use ($array): void {
    expect($array)->not->toHaveSnakeCaseKeys('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($array): void {
    expect($array)->not->toHaveSnakeCaseKeys();
})->throws(ExpectationFailedException::class);
