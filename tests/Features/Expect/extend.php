<?php

expect()->extend('toBeAMacroExpectation', function (): object {
    $this->toBeTrue();

    return $this;
});

expect()->extend('toBeAMacroExpectationWithArguments', function (bool $value): object {
    $this->toBe($value);

    return $this;
});

it('macros true is true', function (): void {
    expect(true)->toBeAMacroExpectation();
});

it('macros false is not true', function (): void {
    expect(false)->not->toBeAMacroExpectation();
});

it('macros true is true with argument', function (): void {
    expect(true)->toBeAMacroExpectationWithArguments(true);
});

it('macros false is not true with argument', function (): void {
    expect(false)->not->toBeAMacroExpectationWithArguments(true);
});
