<?php

use PHPUnit\Framework\ExpectationFailedException;
use Pest\Exceptions\InvalidMethod;

test('passes strings', function () {
    expect('Nuno')->toContainOp('greater than or equal', 1, 'Nu');
});

test('not success with greater than', function () {
    expect([1, 42, 2, 42, 31, 42])->not->toContainOp('greater than', 2, 31);
});

test('passes with greater than', function () {
    expect([[1, 2, 3], 2, 42, [1, 2, 3]])->toContainOp('greater than', 1, [1, 2, 3]);
});

test('not success with greater than or equal', function () {
    expect([1, 42, 2, 42, 31, 42])->not->toContainOp('greater than or equal', 2, 31);
});

test('passes with greater than or equal', function () {
    expect([[1, 2, 3], 2, 42, [1, 2, 3]])->toContainOp('greater than or equal', 1, [1, 2, 3]);
});

test('not success with less than', function () {
    expect([1, 42, 2, 42, 31, 42])->not->toContainOp('less than', 2, 42);
});

test('passes with less than', function () {
    expect([[1, 2, 3], 2, 42, [1, 2, 3]])->toContainOp('less than', 3, [1, 2, 3]);
});

test('not success with less than or equal', function () {
    expect([1, 42, 2, 42, 31, 42])->not->toContainOp('less than or equal', 2, 42);
});

test('passes with less than or equal', function () {
    expect([[1, 2, 3], 2, 42, [1, 2, 3]])->toContainOp('less than or equal', 2, [1, 2, 3]);
});

test('not success with equals', function () {
    expect([1, 2, 42, 2, 97, 31, 2])->not->toContainOp('equals', 2, 31);
});

test('passes with equals', function () {
    expect([1, 2, 42, 2, 2])->toContainOp('equals', 3, 2);
});

test('failures', function () {
    expect([1, 2, 42, 2])->toContainOp('not exist', 3, 2);
})->throws(InvalidMethod::class);

test('not failures', function () {
    expect([1, 2, 42, 2, 97, 31, 2])->not->toContainOp('not exist', 2, 2);
})->throws(InvalidMethod::class);
