<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Expectation;

it('passes', function (): void {
    expect(Expectation::class)->toHaveLineCountLessThan(2000);
});

it('fails', function (): void {
    expect(Expectation::class)->toHaveLineCountLessThan(10);
})->throws(ArchExpectationFailedException::class);
