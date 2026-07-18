<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(sys_get_temp_dir())->toBeWritableDirectory();
});

test('failures', function (): void {
    expect('/random/path/whatever')->toBeWritableDirectory();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('/random/path/whatever')->toBeWritableDirectory('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(sys_get_temp_dir())->not->toBeWritableDirectory();
})->throws(ExpectationFailedException::class);
