<?php

it('passes when the value is within the specified range', function () {
    expect(5)->toBeWithin(1, 10);
    expect(1)->toBeWithin(1, 10);
    expect(10)->toBeWithin(1, 10);
    expect(3.14)->toBeWithin(3, 4);
});

it('fails when the value is outside the specified range', function () {
    expect(0)->not->toBeWithin(1, 10);
    expect(11)->not->toBeWithin(1, 10);
    expect(2.99)->not->toBeWithin(3, 4);
});

it('works with float values', function () {
    expect(5.5)->toBeWithin(5, 6);
    expect(5.0)->toBeWithin(5, 6);
    expect(6.0)->toBeWithin(5, 6);
});

it('fails when given a non-numeric value', function () {
    expect(fn () => expect('string')->toBeWithin(1, 10))
        ->toThrow(InvalidExpectationValue::class, 'Expected numeric');
});

it('works with negative numbers', function () {
    expect(-5)->toBeWithin(-10, 0);
    expect(-7.5)->toBeWithin(-8, -7);
});
