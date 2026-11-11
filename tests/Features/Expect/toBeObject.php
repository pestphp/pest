<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect((object) ['a' => 1])->toBeObject()
        ->and(['a' => 1])->not->toBeObject();
});

test('failures', function (): void {
    expect()->toBeObject();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect()->toBeObject('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect((object) 'ciao')->not->toBeObject();
})->throws(ExpectationFailedException::class);
