<?php

$foo = new \stdClass;
$foo->bar = 0;

beforeAll(function () use ($foo) {
    $foo->bar++;
});

it('gets executed before tests', function () use ($foo) {
    expect($foo)->bar->toBe(1);

    $foo->bar = 'changed';
});

it('do not get executed before each test', function () use ($foo) {
    expect($foo)->bar->toBe('changed');
});
