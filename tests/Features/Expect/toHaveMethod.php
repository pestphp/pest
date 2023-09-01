<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('class has method')
    ->expect('Tests\Fixtures\Arch\ToHaveMethod\HasMethod\HasMethod')
    ->toHaveMethod('foo');

test('opposite class has method')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\Fixtures\Arch\ToHaveMethod\HasMethod\HasMethod')
    ->not->toHaveMethod('foo');

test('class has method via a parent class')
    ->expect('Tests\Fixtures\Arch\ToHaveMethod\HasMethod\HasMethodViaParent')
    ->toHaveMethod('foo');

test('class has method via a trait')
    ->expect('Tests\Fixtures\Arch\ToHaveMethod\HasMethod\HasMethodViaTrait')
    ->toHaveMethod('foo');

test('failure when the class has no method')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\Fixtures\Arch\ToHaveMethod\HasNoMethod\HasNoMethodClass')
    ->toHaveMethod('foo');

test('class has no method')
    ->expect('Tests\Fixtures\Arch\ToHaveMethod\HasNoMethod\HasNoMethodClass')
    ->not->toHaveMethod('foo');
