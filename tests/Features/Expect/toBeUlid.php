<?php

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('failures with wrong type', function () {
    expect([])->toBeUlid();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('pass', function () {
    expect('1wnys2cgs627qaa5m4d69qh346')->toBeUlid(); // Timestamp 4084-06-09T15:09:55.366Z
    expect('00010nrgs647qae044d69qh346')->toBeUlid(); // Timestamp 1970-01-13T16:36:05.542Z
    expect('1ze0wnbant7sra7jxtzxna7cmf')->toBeUlid(); // Timestamp 4180-04-29T21:53:22.618Z
    expect('6a1a12hkfp87dv6jy2yca8aybc')->toBeUlid(); // Timestamp 9009-07-17T20:09:55.958Z
    expect('53ahvtxfxhbwqbb92725cmv1az')->toBeUlid(); // Timestamp 7660-10-06T00:48:41.393Z
    expect('0ywgs67kttdzc8yhw4d69qh346')->toBeUlid(); // Timestamp 3046-04-28T14:19:38.714Z
    expect('01h8nyy2fjea6bxjy3ynmxj546')->toBeUlid(); // Timestamp 2023-08-25T09:03:20.562Z
    expect('0024h36h2ngsvrh6daqf6dvvqz')->toBeUlid(); // Timestamp 1972-05-01T17:10:29.205Z
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
