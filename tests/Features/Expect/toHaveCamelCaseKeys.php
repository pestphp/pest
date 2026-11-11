<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

$array = [
    'camel' => true,
    'camelCase' => [
        'camel' => true,
        'camelCase' => [
            'camel' => true,
            'camelCase' => true,
        ],
        'list' => [
            'abc',
            'def',
            'ghi',
        ],
    ],
];

test('pass', function () use ($array): void {
    expect($array)->toHaveCamelCaseKeys();
});

test('failures', function (): void {
    expect('not-an-array')->toHaveCamelCaseKeys();
})->throws(InvalidExpectationValue::class);

test('failures with message', function () use ($array): void {
    expect($array)->not->toHaveCamelCaseKeys('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($array): void {
    expect($array)->not->toHaveCamelCaseKeys();
})->throws(ExpectationFailedException::class);
