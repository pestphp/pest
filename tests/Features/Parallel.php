<?php

use Pest\TestSuite;

it('can determine in the test case if it is running in parallel', function () {
    expect(test()->isInParallel())->toBeTrue();
})->skip(!TestSuite::getInstance()->isInParallel);

it('can determine in the test case if it is not running in parallel', function () {
    expect(test()->isInParallel())->toBeFalse();
})->skip(TestSuite::getInstance()->isInParallel);

it('can skip using the test case based on parallel status', function () {
    expect(TestSuite::getInstance()->isInParallel)->toBeFalse();
})->skip(function () { return $this->isInParallel(); });
