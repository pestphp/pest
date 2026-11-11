<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->times = [new DateTimeImmutable, new DateTimeImmutable];
});

test('pass', function (): void {
    expect($this->times)->toContainOnlyInstancesOf(DateTimeImmutable::class)->not->toContainOnlyInstancesOf(DateTime::class);
});

test('failures', function (): void {
    expect($this->times)->toContainOnlyInstancesOf(DateTime::class);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect($this->times)->toContainOnlyInstancesOf(DateTime::class, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect($this->times)->not->toContainOnlyInstancesOf(DateTimeImmutable::class);
})->throws(ExpectationFailedException::class);
