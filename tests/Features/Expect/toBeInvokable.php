<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('class is invokable')
    ->expect('Tests\\Fixtures\\Arch\\ToBeInvokable\\IsInvokable\\InvokableClass')
    ->toBeInvokable();

test('opposite class is invokable')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToBeInvokable\\IsInvokable\\InvokableClass')
    ->not->toBeInvokable();

test('class is invokable via a parent class')
    ->expect('Tests\\Fixtures\\Arch\\ToBeInvokable\\IsInvokable\\InvokableClassViaParent')
    ->toBeInvokable();

test('class is invokable via a trait')
    ->expect('Tests\\Fixtures\\Arch\\ToBeInvokable\\IsInvokable\\InvokableClassViaTrait')
    ->toBeInvokable();

test('failure when the class is not invokable')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToBeInvokable\\IsNotInvokable\\IsNotInvokableClass')
    ->toBeInvokable();

test('class is not invokable')
    ->expect('Tests\\Fixtures\\Arch\\ToBeInvokable\\IsNotInvokable\\IsNotInvokableClass')
    ->not->toBeInvokable();
