<?php

function cacheable(): int
{
    static $value;

    if ($value !== null) {
        return $value;
    }

    if (defined('ISOLATED') && ISOLATED) {
        $value = 1;
    } else {
        $value = 2;
    }

    return $value;
}

test('Set cacheable function', function () {
    define('ISOLATED', true);

    $value = cacheable();

    $this->assertSame(1, $value);
});

test('Cached value will still be set', function () {
    $value = cacheable();

    $this->assertSame(1, $value);
});

test('Cacheable function should return a different value because of process isolation', function () {
    if (!defined('ISOLATED')) {
        define('ISOLATED', false);
    }

    $value = cacheable();

    $this->assertSame(2, $value);
})->runInSeparateProcess();
