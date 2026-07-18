<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    touch($this->tempFile = sys_get_temp_dir().'/fake.file');
});

afterEach(function (): void {
    unlink($this->tempFile);
});

test('pass', function (): void {
    expect($this->tempFile)->toBeReadableFile();
});

test('failures', function (): void {
    expect('/random/path/whatever.file')->toBeReadableFile();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('/random/path/whatever.file')->toBeReadableFile('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect($this->tempFile)->not->toBeReadableFile();
})->throws(ExpectationFailedException::class);
