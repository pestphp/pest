<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(sys_get_temp_dir())->toBeReadableDirectory();
});

test('failures', function () {
    expect('/random/path/whatever')->toBeReadableDirectory();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('/random/path/whatever')->toBeReadableDirectory('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(sys_get_temp_dir())->not->toBeReadableDirectory();
})->throws(ExpectationFailedException::class);
