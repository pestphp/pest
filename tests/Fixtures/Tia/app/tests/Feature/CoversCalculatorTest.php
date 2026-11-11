<?php

declare(strict_types=1);

use Fixture\App\Calculator;

test('adds within a feature test', function (): void {
    expect((new Calculator)->add(10, 5))->toBe(15);
});

test('subtracts within a feature test', function (): void {
    expect((new Calculator)->subtract(10, 5))->toBe(5);
});
