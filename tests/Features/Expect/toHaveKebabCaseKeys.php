<?php

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

$array = [
    'kebab' => true,
    'kebab-case' => [
        'kebab' => true,
        'kebab-case' => [
            'kebab' => true,
            'kebab-case' => true,
        ],
        'list' => [
            'abc',
            'def',
            'ghi',
        ],
    ],
];

test('pass', function () use ($array) {
    expect($array)->toHaveKebabCaseKeys();
});

test('failures', function () {
    expect('not-an-array')->toHaveKebabCaseKeys();
})->throws(InvalidExpectationValue::class);

test('failures with message', function () use ($array) {
    expect($array)->not->toHaveKebabCaseKeys('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($array) {
    expect($array)->not->toHaveKebabCaseKeys();
})->throws(ExpectationFailedException::class);
