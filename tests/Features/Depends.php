<?php

$runCounter = 0;

test('first', function () use (&$runCounter): string {
    expect(true)->toBeTrue();
    $runCounter++;

    return 'first';
});

test('second', function () use (&$runCounter): string {
    expect(true)->toBeTrue();
    $runCounter++;

    return 'second';
});

test('depends', function (): void {
    expect(func_get_args())->toBe(['first', 'second']);
})->depends('first', 'second');

test('depends with ...params', function (string ...$params): void {
    expect(func_get_args())->toBe($params);
})->depends('first', 'second');

test('depends with defined arguments', function (string $first, string $second): void {
    expect($first)->toBe('first')
        ->and($second)->toBe('second');
})->depends('first', 'second');

test('depends run test only once', function () use (&$runCounter): void {
    expect($runCounter)->toBe(2);
})->depends('first', 'second');

it('asserts true is true')->assertTrue(true);
test('depends works with the correct test name')->assertTrue(true)->depends('it asserts true is true');

describe('describe block', function (): void {
    $runCounter = 0;

    test('first in describe', function () use (&$runCounter): void {
        $runCounter++;
        expect(true)->toBeTrue();
    });

    test('second in describe', function () use (&$runCounter): void {
        expect($runCounter)->toBe(1);
        $runCounter++;
    })->depends('first in describe');

    test('third in describe', function () use (&$runCounter): void {
        expect($runCounter)->toBe(2);
    })->depends('second in describe');

    describe('nested describe', function (): void {
        $runCounter = 0;

        test('first in nested describe', function () use (&$runCounter): void {
            $runCounter++;
            expect(true)->toBeTrue();
        });

        test('second in nested describe', function () use (&$runCounter): void {
            expect($runCounter)->toBe(1);
            $runCounter++;
        })->depends('first in nested describe');

        test('third in nested describe', function () use (&$runCounter): void {
            expect($runCounter)->toBe(2);
        })->depends('second in nested describe');
    });
});

test('depends on test after describe block', function () use (&$runCounter): void {
    expect($runCounter)->toBe(2);
})->depends('first', 'second');
