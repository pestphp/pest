<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Expectation;
use Pest\Factories\TestCaseFactory;
use Tests\Fixtures\Inheritance\ExampleTest;

it('passes', function (): void {
    expect(Expectation::class)->toHavePropertiesDocumented()
        ->and(ExampleTest::class)->not->toHavePropertiesDocumented();
});

it('fails 1', function (): void {
    expect(ExampleTest::class)->toHavePropertiesDocumented();
})->throws(ArchExpectationFailedException::class);

it('fails 2', function (): void {
    expect(TestCaseFactory::class)->not->toHavePropertiesDocumented();
})->throws(ArchExpectationFailedException::class);
