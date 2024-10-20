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

test('multiple times with iterator', function (int $iteration) {
    expect($iteration)
        ->toBeNumeric()
        ->toBeGreaterThan(0);
})->repeat(times: 2);

test('multiple times with repeat iterator with single dataset', function (string $letter, int $iteration) {
    expect($letter)
        ->toBeString()
        ->toBeIn(['a', 'b', 'c'])
        ->and($iteration)
        ->toBeNumeric()
        ->toBeGreaterThan(0);
})->repeat(times: 2)->with(['a', 'b', 'c']);

test('multiple times with repeat iterator with multiple dataset', function (string $letterA, string $letterB, int $iteration) {
    expect($letterA)
        ->toBeString()
        ->toBeIn(['a', 'b', 'c'])
        ->and($letterB)
        ->toBeString()
        ->toBeIn(['d', 'e', 'f'])
        ->and($iteration)
        ->toBeNumeric()
        ->toBeGreaterThan(0);
})->repeat(times: 2)->with(['a', 'b', 'c'], ['d', 'e', 'f']);

describe('describe blocks', function () {
    test('multiple times', function () {
        expect(true)->toBeTrue();
    })->repeat(times: 3);

    describe('describe with repeat', function () {
        test('test with no repeat should repeat the number of times specified in the parent describe block', function () {
            expect(true)->toBeTrue();
        });

        test('test with repeat should repeat the number of times specified in the test', function () {
            expect(true)->toBeTrue();
        })->repeat(times: 2);

        describe('nested describe without repeat', function () {
            test("test with no repeat should repeat the number of times specified in the parent's parent describe block", function () {
                expect(true)->toBeTrue();
            });

            test('test with repeat should repeat the number of times specified in the test', function () {
                expect(true)->toBeTrue();
            })->repeat(times: 2);
        });

        describe('nested describe with repeat', function () {
            test('test with no repeat should repeat the number of times specified in the parent describe block', function () {
                expect(true)->toBeTrue();
            });

            test('test with repeat should repeat the number of times specified in the test', function () {
                expect(true)->toBeTrue();
            })->repeat(times: 2);
        })->repeat(times: 2);
    })->repeat(times: 3);
});
