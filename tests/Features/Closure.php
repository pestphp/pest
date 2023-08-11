<?php

test('execute closure and test as arrow function', function (callable $closure, $expected) {
    expect($closure)->toBe($expected);
})->with([
    [fn () => 1, 1],
    [fn () => [], []],
    [fn () => 'foo bar', 'foo bar'],
]);

test('execute closure and test as normal function', function (callable $closure, $expected) {
    expect($closure)->toBe($expected);
})->with([
    [function () {
        return 1;
    }, 1],
    [function () {
        return [];
    }, []],
    [function () {
        return 'foo bar';
    }, 'foo bar'],
]);
