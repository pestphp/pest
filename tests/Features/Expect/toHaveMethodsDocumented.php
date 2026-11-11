<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Configuration;
use Tests\Fixtures\Inheritance\ExampleTest;

it('passes', function (): void {
    expect(ExampleTest::class)->not->toHaveMethodsDocumented();
});

it('fails 1', function (): void {
    expect(ExampleTest::class)->toHaveMethodsDocumented();
})->throws(ArchExpectationFailedException::class);

it('fails 2', function (): void {
    expect(Configuration::class)->not->toHaveMethodsDocumented();
})->throws(ArchExpectationFailedException::class);
