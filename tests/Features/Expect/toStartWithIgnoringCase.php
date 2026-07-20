<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('Laravel')->toStartWithIgnoringCase('lar')
        ->and('PestPHP')->toStartWithIgnoringCase('PEST')
        ->and('PestPHP')->toStartWithIgnoringCase('PestPHP')
        ->and('Framework')->not->toStartWithIgnoringCase('work');
});

test('failures', function (): void {
    expect('Laravel')->toStartWithIgnoringCase('vel');
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('Laravel')->toStartWithIgnoringCase('vel', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('Laravel')->not->toStartWithIgnoringCase('lar');
})->throws(ExpectationFailedException::class);
