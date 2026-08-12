<?php

declare(strict_types=1);

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('192.168.1.1')->toBeIpAddress()
        ->and('::1')->toBeIpAddress()
        ->and('2001:db8::1')->toBeIpAddress();
});

test('failures', function (): void {
    expect('not-an-ip')->toBeIpAddress();
})->throws(ExpectationFailedException::class);

test('failures with invalid type', function (): void {
    expect([])->toBeIpAddress();
})->throws(InvalidExpectationValue::class, 'This expectation may only be used on a value of type [string].');

test('failures with custom message', function (): void {
    expect('not-an-ip')->toBeIpAddress('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('192.168.1.1')->not->toBeIpAddress();
})->throws(ExpectationFailedException::class);
