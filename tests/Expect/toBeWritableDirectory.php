<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(sys_get_temp_dir())->toBeWritableDirectory();
});

test('failures', function () {
    expect('/random/path/whatever')->toBeWritableDirectory();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(sys_get_temp_dir())->not->toBeWritableDirectory();
})->throws(ExpectationFailedException::class);
