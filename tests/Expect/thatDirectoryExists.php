<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    $temp = sys_get_temp_dir();

    expect($temp)->toBeExistingDirectory();
});

test('failures', function () {
    expect('/random/path/whatever')->toBeExistingDirectory();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('.')->not->toBeExistingDirectory();
})->throws(ExpectationFailedException::class);
