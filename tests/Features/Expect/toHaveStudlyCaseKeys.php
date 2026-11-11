<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

$array = [
    'Studly' => true,
    'StudlyCase' => [
        'Studly' => true,
        'StudlyCase' => [
            'Studly' => true,
            'StudlyCase' => true,
        ],
        'List' => [
            'abc',
            'def',
            'ghi',
        ],
    ],
];

test('pass', function () use ($array): void {
    expect($array)->toHaveStudlyCaseKeys();
});

test('failures', function (): void {
    expect('not-an-array')->toHaveStudlyCaseKeys();
})->throws(InvalidExpectationValue::class);

test('failures with message', function () use ($array): void {
    expect($array)->not->toHaveStudlyCaseKeys('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($array): void {
    expect($array)->not->toHaveStudlyCaseKeys();
})->throws(ExpectationFailedException::class);
