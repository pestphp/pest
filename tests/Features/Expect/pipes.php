<?php

declare(strict_types=1);

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertEqualsIgnoringCase;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertSame;

class Number
{
    public function __construct(
        public int $value
    ) {
        // ..
    }
}

class Char
{
    public function __construct(
        public string $value
    ) {
        // ..
    }
}

class Symbol
{
    public function __construct(
        public string $value
    ) {
        // ..
    }
}

class State
{
    public array $runCount = [];

    public array $appliedCount = [];

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->appliedCount = $this->runCount = [
            'char' => 0,
            'number' => 0,
            'wildcard' => 0,
            'symbol' => 0,
        ];
    }
}

$state = new State;

/*
 * Overrides toBe to assert two Characters are the same
 */
expect()->pipe('toBe', function ($next, $expected) use ($state) {
    $state->runCount['char']++;

    if ($this->value instanceof Char) {
        $state->appliedCount['char']++;

        assertInstanceOf(Char::class, $expected);
        assertEquals($this->value->value, $expected->value);

        // returning nothing stops pipeline execution
        return;
    }

    // calling $next(); let the pipeline to keep running
    $next();
});

/*
 * Overrides toBe to assert two Number objects are the same
 */
expect()->intercept('toBe', Number::class, function ($expected) use ($state) {
    $state->runCount['number']++;
    $state->appliedCount['number']++;

    assertInstanceOf(Number::class, $expected);
    assertEquals($this->value->value, $expected->value);
});

/*
 * Overrides toBe to assert all integers are allowed if value is a wildcard (*)
 */
expect()->intercept('toBe', fn ($value, $expected) => $value === '*' && is_numeric($expected), function ($expected) use ($state) {
    $state->runCount['wildcard']++;
    $state->appliedCount['wildcard']++;
});

/*
 * Overrides toBe to assert to Symbols are the same
 */
expect()->pipe('toBe', function ($next, $expected) use ($state) {
    $state->runCount['symbol']++;

    if ($this->value instanceof Symbol) {
        $state->appliedCount['symbol']++;
        assertInstanceOf(Symbol::class, $expected);
        assertEquals($this->value->value, $expected->value);

        return;
    }

    $next();
});

/*
 * Overrides toBe to allow ignoring case when checking strings
 */
expect()->intercept('toBe', fn ($value) => is_string($value), function ($expected, $ignoreCase = false) {
    if ($ignoreCase) {
        assertEqualsIgnoringCase($expected, $this->value);
    } else {
        assertSame($expected, $this->value);
    }
});

test('pipe is applied and can stop pipeline', function () use ($state) {
    $char = new Char('A');

    $state->reset();

    expect($char)->toBe(new Char('A'))
        ->and($state)
        ->runCount->toMatchArray([
            'char' => 1,
            'number' => 0,
            'wildcard' => 0,
            'symbol' => 0,
        ])
        ->appliedCount->toMatchArray([
            'char' => 1,
            'number' => 0,
            'wildcard' => 0,
            'symbol' => 0,
        ]);
});

test('pipe is run and can let the pipeline keep going', function () use ($state) {
    $state->reset();

    expect(3)->toBe(3)
        ->and($state)
        ->runCount->toMatchArray([
            'char' => 1,
            'number' => 0,
            'wildcard' => 0,
            'symbol' => 1,
        ])
        ->appliedCount->toMatchArray([
            'char' => 0,
            'number' => 0,
            'wildcard' => 0,
            'symbol' => 0,
        ]);
});

test('pipe works with negated expectation', function () use ($state) {
    $char = new Char('A');

    $state->reset();

    expect($char)->not->toBe(new Char('B'))
        ->and($state)
        ->runCount->toMatchArray([
            'char' => 1,
            'number' => 0,
            'wildcard' => 0,
            'symbol' => 0,
        ])
        ->appliedCount->toMatchArray([
            'char' => 1,
            'number' => 0,
            'wildcard' => 0,
            'symbol' => 0,
        ]);
});

test('interceptor is applied', function () use ($state) {
    $number = new Number(1);

    $state->reset();

    expect($number)->toBe(new Number(1))
        ->and($state)
        ->runCount->toHaveKey('number', 1)
        ->appliedCount->toHaveKey('number', 1);
});

test('interceptor stops the pipeline', function () use ($state) {
    $number = new Number(1);

    $state->reset();

    expect($number)->toBe(new Number(1))
        ->and($state)
        ->runCount->toMatchArray([
            'char' => 1,
            'number' => 1,
            'wildcard' => 0,
            'symbol' => 0,
        ])
        ->appliedCount->toMatchArray([
            'char' => 0,
            'number' => 1,
            'wildcard' => 0,
            'symbol' => 0,
        ]);
});

test('interceptor is called only when filter is met', function () use ($state) {
    $state->reset();

    expect(1)->toBe(1)
        ->and($state)
        ->runCount->toHaveKey('number', 0)
        ->appliedCount->toHaveKey('number', 0);
});

test('interceptor can be filtered with a closure', function () use ($state) {
    $state->reset();

    expect('*')->toBe(1)
        ->and($state)
        ->runCount->toHaveKey('wildcard', 1)
        ->appliedCount->toHaveKey('wildcard', 1);
});

test('interceptor can be filter the expected parameter as well', function () use ($state) {
    $state->reset();

    expect('*')->toBe('*')
        ->and($state)
        ->runCount->toHaveKey('wildcard', 0)
        ->appliedCount->toHaveKey('wildcard', 0);
});

test('interceptor works with negated expectation', function () {
    $char = new Number(1);

    expect($char)->not->toBe(new Char('B'));
});

test('intercept can add new parameters to the expectation', function () {
    $ignoreCase = true;

    expect('Foo')->toBe('foo', $ignoreCase);
});
