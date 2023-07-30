<?php

test('once', function () {
    expect(true)->toBeTrue();
})->repeat(times: 1);

test('multiple times', function () {
    expect(true)->toBeTrue();
})->repeat(times: 5);

test('multiple times with single dataset', function (int $number) {
    expect([1, 2, 3])->toContain($number);
})->repeat(times: 6)->with(['a' => 1, 'b' => 2, 'c' => 3]);

test('multiple times with multiple dataset', function (int $numberA, int $numberB) {
    expect([1, 2, 3])->toContain($numberA)
        ->and([4, 5, 6])->toContain($numberB);
})->repeat(times: 7)->with(['a' => 1, 'b' => 2, 'c' => 3], [4, 5, 6]);
