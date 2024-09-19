<?php

use function PHPUnit\Framework\assertTrue;

test()->extend('fooBar', function () {
    return $this->with([
        'foo',
        'bar',
    ]);
});

it('uses fooBar extension', function ($value) {
    assertTrue(in_array($value, [
        'foo',
        'bar',
    ]));
})->fooBar();
