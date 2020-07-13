<?php

use Pest\Actions\AddsTests;
use Pest\Expectation;
use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    $expected = [new Expectation('whatever')];

    expect($expected)->toContainOnlyInstancesOf(Expectation::class);
});

test('failures', function () {
    $expected = [new Expectation('whatever')];

    expect($expected)->toContainOnlyInstancesOf(AddsTests::class);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    $expected = [new Expectation('whatever')];

    expect($expected)->not->toContainOnlyInstancesOf(Expectation::class);
})->throws(ExpectationFailedException::class);
