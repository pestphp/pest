<?php

test('once', function (): void {
    expect(true)->toBeTrue();
})->repeat(times: 1);

test('multiple times', function (): void {
    expect(true)->toBeTrue();
})->repeat(times: 5);

test('multiple times with single dataset', function (int $number): void {
    expect([1, 2, 3])->toContain($number);
})->repeat(times: 6)->with(['a' => 1, 'b' => 2, 'c' => 3]);

test('multiple times with multiple dataset', function (int $numberA, int $numberB): void {
    expect([1, 2, 3])->toContain($numberA)
        ->and([4, 5, 6])->toContain($numberB);
})->repeat(times: 7)->with(['a' => 1, 'b' => 2, 'c' => 3], [4, 5, 6]);

test('multiple times with iterator', function (int $iteration): void {
    expect($iteration)
        ->toBeNumeric()
        ->toBeGreaterThan(0);
})->repeat(times: 2);

test('multiple times with repeat iterator with single dataset', function (string $letter, int $iteration): void {
    expect($letter)
        ->toBeString()
        ->toBeIn(['a', 'b', 'c'])
        ->and($iteration)
        ->toBeNumeric()
        ->toBeGreaterThan(0);
})->repeat(times: 2)->with(['a', 'b', 'c']);

test('multiple times with repeat iterator with multiple dataset', function (string $letterA, string $letterB, int $iteration): void {
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

describe('describe blocks', function (): void {
    test('multiple times', function (): void {
        expect(true)->toBeTrue();
    })->repeat(times: 3);

    describe('describe with repeat', function (): void {
        test('test with no repeat should repeat the number of times specified in the parent describe block', function (): void {
            expect(true)->toBeTrue();
        });

        test('test with repeat should repeat the number of times specified in the test', function (): void {
            expect(true)->toBeTrue();
        })->repeat(times: 2);

        describe('nested describe without repeat', function (): void {
            test("test with no repeat should repeat the number of times specified in the parent's parent describe block", function (): void {
                expect(true)->toBeTrue();
            });

            test('test with repeat should repeat the number of times specified in the test', function (): void {
                expect(true)->toBeTrue();
            })->repeat(times: 2);
        });

        describe('nested describe with repeat', function (): void {
            test('test with no repeat should repeat the number of times specified in the parent describe block', function (): void {
                expect(true)->toBeTrue();
            });

            test('test with repeat should repeat the number of times specified in the test', function (): void {
                expect(true)->toBeTrue();
            })->repeat(times: 2);
        })->repeat(times: 2);
    })->repeat(times: 3);
});

describe('matching describe blocks', function (): void {
    describe('describe block', function (): void {
        it('should repeat the number of times specified in the parent describe block', function (): void {
            expect(true)->toBeTrue();
        });
    })->repeat(times: 3);

    describe('describe block', function (): void {
        test('should not repeat the number of times of the describe block with the same name', function (): void {
            expect(true)->toBeTrue();
        });
    });
});
