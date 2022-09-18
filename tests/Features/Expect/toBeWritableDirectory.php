<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(sys_get_temp_dir())->toBeWritableDirectory();
});

test('failures', function () {
    expect('/random/path/whatever')->toBeWritableDirectory();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('/random/path/whatever')->toBeWritableDirectory('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(sys_get_temp_dir())->not->toBeWritableDirectory();
})->throws(ExpectationFailedException::class);
