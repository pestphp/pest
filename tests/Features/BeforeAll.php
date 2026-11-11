<?php

$foo = new stdClass;
$foo->bar = 0;

beforeAll(function () use ($foo): void {
    $foo->bar++;
});

it('gets executed before tests', function () use ($foo): void {
    expect($foo)->bar->toBe(1);

    $foo->bar = 'changed';
});

it('do not get executed before each test', function () use ($foo): void {
    expect($foo)->bar->toBe('changed');
});
