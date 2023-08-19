<?php

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

test('pass', function () use ($array) {
    expect($array)->toHaveSnakeCaseKeys();
});

test('failures', function () {
    expect('not-an-array')->toHaveSnakeCaseKeys();
})->throws(InvalidExpectationValue::class);

test('failures with message', function () use ($array) {
    expect($array)->not->toHaveSnakeCaseKeys('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($array) {
    expect($array)->not->toHaveSnakeCaseKeys();
})->throws(ExpectationFailedException::class);
