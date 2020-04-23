<?php

$foo      = new \stdClass();
$foo->bar = 0;

beforeAll(function () use ($foo) {
    $foo->bar++;
});

it('gets executed before tests', function () use ($foo) {
    assertEquals($foo->bar, 1);

    $foo->bar = 'changed';
});

it('do not get executed before each test', function () use ($foo) {
    assertEquals($foo->bar, 'changed');
});
