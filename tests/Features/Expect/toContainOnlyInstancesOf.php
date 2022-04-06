<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function () {
    $this->times = [new DateTimeImmutable(), new DateTimeImmutable()];
});

test('pass', function () {
    expect($this->times)->toContainOnlyInstancesOf(DateTimeImmutable::class);
    expect($this->times)->not->toContainOnlyInstancesOf(DateTime::class);
});

test('failures', function () {
    expect($this->times)->toContainOnlyInstancesOf(DateTime::class);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect($this->times)->not->toContainOnlyInstancesOf(DateTimeImmutable::class);
})->throws(ExpectationFailedException::class);
