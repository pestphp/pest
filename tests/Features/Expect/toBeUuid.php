<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('failures with wrong type', function (): void {
    expect([])->toBeUuid();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [string].');

test('pass', function (): void {
    expect('3cafb226-4326-11ee-a516-846993788c86')->toBeUuid()
        ->and('0000415c-4326-21ee-a700-846993788c86')->toBeUuid()
        ->and('3f703955-aaba-3e70-a3cb-baff6aa3b28f')->toBeUuid()
        ->and('ca0a8228-cdf6-41db-b34b-c2f31485796c')->toBeUuid()
        ->and('a35477ae-bfb1-5f2e-b5a4-4711594d855f')->toBeUuid()
        ->and('1ee43263-cf5a-6fd8-8f47-846993788c86')->toBeUuid()
        ->and('018a2bef-09f2-728c-becb-c3f569d91486')->toBeUuid()
        ->and('00112233-4455-8677-8899-aabbccddeeff')->toBeUuid();
});

test('failures', function (): void {
    expect('foo')->toBeUuid();
})->throws(ExpectationFailedException::class);

test('failures with message', function (): void {
    expect('bar')->toBeUuid('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('foo')->not->toBeUuid();
});
