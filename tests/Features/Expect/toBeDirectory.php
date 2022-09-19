<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    $temp = sys_get_temp_dir();

    expect($temp)->toBeDirectory();
});

test('failures', function () {
    expect('/random/path/whatever')->toBeDirectory();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('/random/path/whatever')->toBeDirectory('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('.')->not->toBeDirectory();
})->throws(ExpectationFailedException::class);
