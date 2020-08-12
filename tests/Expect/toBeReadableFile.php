<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function () {
    touch($this->tempFile = sys_get_temp_dir() . '/fake.file');
});

afterEach(function () {
    unlink($this->tempFile);
});

test('pass', function () {
    expect($this->tempFile)->toBeReadableFile();
});

test('failures', function () {
    expect('/random/path/whatever.file')->toBeReadableFile();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect($this->tempFile)->not->toBeReadableFile();
})->throws(ExpectationFailedException::class);
