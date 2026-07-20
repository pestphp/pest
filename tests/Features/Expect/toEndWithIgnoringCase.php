<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('Laravel')->toEndWithIgnoringCase('VEL')
        ->and('PestPHP')->toEndWithIgnoringCase('php')
        ->and('PestPHP')->toEndWithIgnoringCase('PestPHP')
        ->and('Framework')->not->toEndWithIgnoringCase('frame');
});

test('failures', function (): void {
    expect('Laravel')->toEndWithIgnoringCase('lar');
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('Laravel')->toEndWithIgnoringCase('lar', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('Laravel')->not->toEndWithIgnoringCase('VEL');
})->throws(ExpectationFailedException::class);
