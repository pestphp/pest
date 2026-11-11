<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

$resource = tmpfile();

afterAll(function () use ($resource): void {
    fclose($resource);
});

test('pass', function () use ($resource): void {
    expect($resource)->toBeResource()
        ->and(null)->not->toBeResource();
});

test('failures', function (): void {
    expect()->toBeResource();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect()->toBeResource('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($resource): void {
    expect($resource)->not->toBeResource();
})->throws(ExpectationFailedException::class);
