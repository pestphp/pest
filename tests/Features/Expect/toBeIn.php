<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function () {
    expect('a')->toBeIn(['a', 'b', 'c']);
});

test('failures', function () {
    expect('d')->toBeIn(['a', 'b', 'c']);
})->throws(ExpectationFailedException::class);
