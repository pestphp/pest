<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Expectation;
use Tests\Fixtures\Inheritance\ExampleTest;

it('passes', function () {
    expect(Expectation::class)->toHaveAllPropertiesDocumented();
});

it('fails', function () {
    expect(ExampleTest::class)->toHaveAllPropertiesDocumented();
})->throws(ArchExpectationFailedException::class);
