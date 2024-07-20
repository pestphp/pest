<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Expectation;
use Pest\Factories\TestCaseFactory;
use Tests\Fixtures\Inheritance\ExampleTest;

it('passes', function () {
    expect(Expectation::class)->toHavePropertiesDocumented()
        ->and(ExampleTest::class)->not->toHavePropertiesDocumented();
});

it('fails 1', function () {
    expect(ExampleTest::class)->toHavePropertiesDocumented();
})->throws(ArchExpectationFailedException::class);

it('fails 2', function () {
    expect(TestCaseFactory::class)->not->toHavePropertiesDocumented();
})->throws(ArchExpectationFailedException::class);
