<?php

expect()->extend('toBeAMacroExpectation', function () {
    $this->toBeTrue();

    return $this;
});

expect()->extend('toBeAMacroExpectationWithArguments', function (bool $value) {
    $this->toBe($value);

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
