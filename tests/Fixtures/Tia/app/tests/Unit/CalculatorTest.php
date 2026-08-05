<?php

declare(strict_types=1);

use Fixture\App\Calculator;

// `test()` rather than `it()`: the harness seeds results by test id, and `it()`
// would prefix every description with `it `, leaving Project::TESTS a step away
// from what is written here.
test('adds two numbers', function (): void {
    expect((new Calculator)->add(1, 2))->toBe(3);
});

test('subtracts two numbers', function (): void {
    expect((new Calculator)->subtract(3, 1))->toBe(2);
});
