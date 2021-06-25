<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function () {
    touch($this->tempFile = sys_get_temp_dir() . '/fake.file');
});

afterEach(function () {
    unlink($this->tempFile);
});

test('pass', function () {
    expect($this->tempFile)->toBeWritableFile();
});

test('failures', function () {
    expect('/random/path/whatever.file')->toBeWritableFile();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect($this->tempFile)->not->toBeWritableFile();
})->throws(ExpectationFailedException::class);
