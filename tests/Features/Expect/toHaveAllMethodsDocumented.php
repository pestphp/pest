<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Expectation;
use Tests\Fixtures\Inheritance\ExampleTest;

it('passes', function () {
    expect(Expectation::class)->toHaveAllMethodsDocumented();
});

it('fails', function () {
    expect(ExampleTest::class)->toHaveAllMethodsDocumented();
})->throws(ArchExpectationFailedException::class);
