<?php

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('failures with wrong type', function () {
    expect([])->toBeUlid();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('pass', function () {
    expect('01ARZ3NDEKTSV4RRFFQ69G5FAV')->toBeUlid();
    expect('01BX5ZZKBKACTAV9WEVGEMMVRE')->toBeUlid();
    expect('7ZZZZZZZZZ0000000000000000')->toBeUlid();
});

test('failures', function () {
    expect('foo')->toBeUlid();
})->throws(ExpectationFailedException::class);

test('failures with message', function () {
    expect('bar')->toBeUlid('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('foo')->not->toBeUlid();
});
