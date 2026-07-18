<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToHaveMethod\HasMethod\HasMethod;
use Tests\Fixtures\Arch\ToHaveMethod\HasMethod\HasMethodViaParent;
use Tests\Fixtures\Arch\ToHaveMethod\HasMethod\HasMethodViaTrait;
use Tests\Fixtures\Arch\ToHaveMethod\HasNoMethod\HasNoMethodClass;

test('class has method')
    ->expect(HasMethod::class)
    ->toHaveMethods(['foo']);

test('opposite class has method')
    ->throws(ArchExpectationFailedException::class)
    ->expect(HasMethod::class)
    ->not->toHaveMethods(['foo']);

test('class has method via a parent class')
    ->expect(HasMethodViaParent::class)
    ->toHaveMethods(['foo']);

test('class has method via a trait')
    ->expect(HasMethodViaTrait::class)
    ->toHaveMethods(['foo']);

test('failure when the class has no method')
    ->throws(ArchExpectationFailedException::class)
    ->expect(HasNoMethodClass::class)
    ->toHaveMethods(['foo']);

test('class has no method')
    ->expect(HasNoMethodClass::class)
    ->not->toHaveMethods(['foo']);
