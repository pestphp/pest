<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    $temp = sys_get_temp_dir();

    expect($temp)->toBeDirectory();
});

test('failures', function (): void {
    expect('/random/path/whatever')->toBeDirectory();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('/random/path/whatever')->toBeDirectory('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('.')->not->toBeDirectory();
})->throws(ExpectationFailedException::class);
