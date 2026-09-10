<?php

declare(strict_types=1);

test('example test ', function () {
    expect(true)->toBeTrue();
});

test('example test', function () {
    expect(true)->toBeTrue();
});

test('example test with dataset ', function (int $number) {
    expect($number)->toBe(1);
})->with([1]);
