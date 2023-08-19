<?php

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

test('pass', function () use ($array) {
    expect($array)->toHaveStudlyCaseKeys();
});

test('failures', function () {
    expect('not-an-array')->toHaveStudlyCaseKeys();
})->throws(InvalidExpectationValue::class);

test('failures with message', function () use ($array) {
    expect($array)->not->toHaveStudlyCaseKeys('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($array) {
    expect($array)->not->toHaveStudlyCaseKeys();
})->throws(ExpectationFailedException::class);
