<?php

/* @min-php-version 8.1 */

enum Colors
{
    case red;
    case green;
    case white;
}
enum BackedColors: string
{
    case red   = 'Red';
    case green = 'Green';
    case white = 'White';
}

it('formats enums data', function () {
    expect(true)->toBeTrue();
})->with([
    [[Colors::green, Colors::white, BackedColors::green]],
    Colors::red,
    Colors::white,
    BackedColors::green,
    BackedColors::red,
    BackedColors::white,
]);
