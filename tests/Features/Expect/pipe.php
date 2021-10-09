<?php

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertIsNumeric;

class Number
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

class Character
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

class Symbol
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

class State
{
    public $runCount     = [];
    public $appliedCount = [];

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->runCount = [
            'character' => 0,
            'number'    => 0,
            'wildcard'  => 0,
            'symbol'    => 0,
        ];

        $this->appliedCount = [
            'character' => 0,
            'number'    => 0,
            'wildcard'  => 0,
            'symbol'    => 0,
        ];
    }
}

$state = new State();

/*
 * Overrides toBe to assert two Characters are the same
 */
expect()->pipe('toBe', function ($next, $expected) use ($state) {
    $state->runCount['character']++;

    if ($this->value instanceof Character) {
        $state->appliedCount['character']++;
        assertInstanceOf(Character::class, $expected);
        assertEquals($this->value->value, $expected->value);

        return;
    }

    $next();
});

/*
 * Overrides toBe to assert two Numbers are the same
 */
expect()->intercept('toBe', Number::class, function ($expected) use ($state) {
    $state->runCount['number']++;
    $state->appliedCount['number']++;
    assertEquals($this->value->value, $expected->value);
});

/*
 * Overrides toBe to assert all integers are allowed if value is an '*'
 */
expect()->intercept('toBe', function ($value) {
    return $value === '*';
}, function ($expected) use ($state) {
    $state->runCount['wildcard']++;
    $state->appliedCount['wildcard']++;
    assertIsNumeric($expected);
});

/*
 * Overrides toBe to assert two Symbols are the same
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

test('pipe is applied and can stop pipeline', function () use ($state) {
    $letter = new Character('A');

    $state->reset();

    expect($letter)->toBe(new Character('A'))
        ->and($state)
        ->runCount->toMatchArray([
            'character' => 1,
            'number'    => 0,
            'wildcard'  => 0,
            'symbol'    => 0,
        ])
        ->appliedCount->toMatchArray([
            'character' => 1,
            'number'    => 0,
            'wildcard'  => 0,
            'symbol'    => 0,
        ]);
});

test('interceptor works with negated expectation', function () {
    $letter = new Number(1);

    expect($letter)->not->toBe(new Character('B'));
});

test('pipe works with negated expectation', function () {
    $letter = new Character('A');

    expect($letter)->not->toBe(new Character('B'));
});

test('pipe is run and can let the pipeline keep going', function () use ($state) {
    $state->reset();

    expect(3)->toBe(3)
        ->and($state)
        ->runCount->toMatchArray([
            'character' => 1,
            'number'    => 0,
            'wildcard'  => 0,
            'symbol'    => 1,
        ])
        ->appliedCount->toMatchArray([
            'character' => 0,
            'number'    => 0,
            'wildcard'  => 0,
            'symbol'    => 0,
        ]);
});

test('intercept is applied', function () use ($state) {
    $number = new Number(1);

    $state->reset();

    expect($number)->toBe(new Number(1))
        ->and($state)
        ->runCount->toHaveKey('number', 1)
        ->appliedCount->toHaveKey('number', 1);
});

test('intercept stops the pipeline', function () use ($state) {
    $number = new Number(1);

    $state->reset();

    expect($number)->toBe(new Number(1))
        ->and($state)
        ->runCount->toMatchArray([
            'character' => 1,
            'number'    => 1,
            'wildcard'  => 0,
            'symbol'    => 0,
        ])
        ->appliedCount->toMatchArray([
            'character' => 0,
            'number'    => 1,
            'wildcard'  => 0,
            'symbol'    => 0,
        ]);
});

test('interception is called only when filter is met', function () use ($state) {
    $state->reset();

    expect(1)->toBe(1)
        ->and($state)
        ->runCount->toHaveKey('number', 0)
        ->appliedCount->toHaveKey('number', 0);
});

test('intercept can be filtered with a closure', function () use ($state) {
    $state->reset();

    expect('*')->toBe(1)
        ->and($state)
        ->runCount->toHaveKey('wildcard', 1)
        ->appliedCount->toHaveKey('wildcard', 1);
});
