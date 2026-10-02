<?php

use PHPUnit\Framework\ExpectationFailedException;
use Tests\Fixtures\Arch\ToUse\CleanClass;
use Tests\Fixtures\Arch\ToUse\Dependencies\Dependency;
use Tests\Fixtures\Arch\ToUse\UsesDependency;

test('single target fails when it uses the dependency', function (): void {
    expect(UsesDependency::class)->not->toUse(Dependency::class);
})->throws(ExpectationFailedException::class);

test('multi target fails when any target uses the dependency', function (): void {
    expect([UsesDependency::class, CleanClass::class])->not->toUse(Dependency::class);
})->throws(ExpectationFailedException::class);

test('multi target fails regardless of the violating target position', function (): void {
    expect([CleanClass::class, UsesDependency::class])->not->toUse(Dependency::class);
})->throws(ExpectationFailedException::class);

test('multi target passes when no target uses the dependency', function (): void {
    expect([CleanClass::class])->not->toUse(Dependency::class);
});
