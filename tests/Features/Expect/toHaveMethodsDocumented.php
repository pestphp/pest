<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Configuration;
use Pest\Expectation;
use Tests\Fixtures\Inheritance\ExampleTest;

it('passes', function () {
    expect(Expectation::class)->toHaveMethodsDocumented()
        ->and(ExampleTest::class)->not->toHaveMethodsDocumented();
});

it('fails 1', function () {
    expect(ExampleTest::class)->toHaveMethodsDocumented();
})->throws(ArchExpectationFailedException::class);

it('fails 2', function () {
    expect(Configuration::class)->not->toHaveMethodsDocumented();
})->throws(ArchExpectationFailedException::class);
