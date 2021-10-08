<?php

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;

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

expect()->decorate('toBe', function ($next, $expected) {
    if ($this->value instanceof Character) {
        assertInstanceOf(Character::class, $expected);
        assertEquals($this->value->value, $expected->value);

        return;
    }

    $next($expected);
});

expect()->decorate('toBe', function ($next, $expected) {
    if ($this->value instanceof Number) {
        assertInstanceOf(Number::class, $expected);
        assertEquals($this->value->value, $expected->value);

        return;
    }

    $next($expected);
});

test('pass', function () {
    $number = new Number(1);

    $letter = new Character('A');

    expect($number)->toBe(new Number(1));
    expect($letter)->toBe(new Character('A'));
    expect(3)->toBe(3);
});
