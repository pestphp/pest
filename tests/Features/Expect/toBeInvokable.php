<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToBeInvokable\IsInvokable\InvokableClass;
use Tests\Fixtures\Arch\ToBeInvokable\IsInvokable\InvokableClassViaParent;
use Tests\Fixtures\Arch\ToBeInvokable\IsInvokable\InvokableClassViaTrait;
use Tests\Fixtures\Arch\ToBeInvokable\IsNotInvokable\IsNotInvokableClass;

test('class is invokable')
    ->expect(InvokableClass::class)
    ->toBeInvokable();

test('opposite class is invokable')
    ->throws(ArchExpectationFailedException::class)
    ->expect(InvokableClass::class)
    ->not->toBeInvokable();

test('class is invokable via a parent class')
    ->expect(InvokableClassViaParent::class)
    ->toBeInvokable();

test('class is invokable via a trait')
    ->expect(InvokableClassViaTrait::class)
    ->toBeInvokable();

test('failure when the class is not invokable')
    ->throws(ArchExpectationFailedException::class)
    ->expect(IsNotInvokableClass::class)
    ->toBeInvokable();

test('class is not invokable')
    ->expect(IsNotInvokableClass::class)
    ->not->toBeInvokable();
