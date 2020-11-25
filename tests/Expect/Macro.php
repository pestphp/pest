<?php

use Pest\Expectation;
use PHPUnit\Framework\Assert;

Expectation::macro('toBeAMacroExpectation', function () {
    Assert::assertTrue($this->value);

    return $this;
});

Expectation::macro('toBeAMacroExpectationWithArguments', function (bool $value) {
    Assert::assertSame($value, $this->value);

    return $this;
});

it('macros true is true', function () {
    expect(true)->toBeAMacroExpectation();
});

it('macros false is not true', function () {
    expect(false)->not->toBeAMacroExpectation();
});

it('macros true is true with argument', function () {
    expect(true)->toBeAMacroExpectationWithArguments(true);
});

it('macros false is not true with argument', function () {
    expect(false)->not->toBeAMacroExpectationWithArguments(true);
});
