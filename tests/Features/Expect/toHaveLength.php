<?php

use PHPUnit\Framework\ExpectationFailedException;

it('passes', function ($value) {
    expect($value)->toHaveLength(9);
})->with([
    'Fortaleza',
    'Sollefteå',
    'Ιεράπετρα',
    (object) [1, 2, 3, 4, 5, 6, 7, 8, 9],
]);

it('passes with array', function () {
    expect([1, 2, 3])->toHaveLength(3);
});

it('passes with *not*', function () {
    expect('')->not->toHaveLength(1);
});

it('properly fails with *not*', function () {
    expect('pest')->not->toHaveLength(4, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

it('fails', function () {
    expect([1, 1.5, true, null])->toHaveLength(1);
})->throws(ExpectationFailedException::class);

it('fails with message', function () {
    expect([1, 1.5, true, null])->toHaveLength(1, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');
