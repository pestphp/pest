<?php

declare(strict_types=1);

it('can pass with comparison', function () {
    expect(true)->toEqual(true);
});

test('can also pass', function () {
    expect("string")->toBeString();
});

test('can pass with dataset', function ($value) {
    expect($value)->toEqual(true);
})->with([true]);
