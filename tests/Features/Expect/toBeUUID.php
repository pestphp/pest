<?php

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('failures with wrong type', function () {
    expect([])->toBeUUID();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('pass', function () {
    expect('3cafb226-4326-11ee-a516-846993788c86')->toBeUUID(); // version 1
    expect('0000415c-4326-21ee-a700-846993788c86')->toBeUUID(); // version 2
    expect('3f703955-aaba-3e70-a3cb-baff6aa3b28f')->toBeUUID(); // version 3
    expect('ca0a8228-cdf6-41db-b34b-c2f31485796c')->toBeUUID(); // version 4
    expect('a35477ae-bfb1-5f2e-b5a4-4711594d855f')->toBeUUID(); // version 5
    expect('1ee43263-cf5a-6fd8-8f47-846993788c86')->toBeUUID(); // version 6
    expect('018a2bef-09f2-728c-becb-c3f569d91486')->toBeUUID(); // version 7
    expect('00112233-4455-8677-8899-aabbccddeeff')->toBeUUID(); // version 8
});

test('failures', function () {
    expect('foo')->toBeUUID();
})->throws(ExpectationFailedException::class);

test('failures with message', function () {
    expect('bar')->toBeUUID('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('foo')->not->toBeUUID();
});
