<?php

use PHPUnit\Framework\ExpectationFailedException;

$anonymousClass = new class()
{
};

$stdClass = new stdClass();

class NotAnonymousClass
{
}

test('class is anonymous')
    ->expect($anonymousClass)
    ->toBeAnonymous();

test('opposite class is anonymous')
    ->throws(ExpectationFailedException::class)
    ->expect($anonymousClass)
    ->not
    ->toBeAnonymous();

test('failure when the class is not anonymous')
    ->throws(ExpectationFailedException::class)
    ->expect($stdClass)
    ->expect(new NotAnonymousClass)
    ->toBeAnonymous();

test('failure when the class is not anonymous with custom message')
    ->throws(ExpectationFailedException::class, 'Oh no!')
    ->expect($stdClass)
    ->expect(new NotAnonymousClass)
    ->toBeAnonymous('Oh no!');

test('class is not anonymous')
    ->expect(new NotAnonymousClass)
    ->expect($stdClass)
    ->not
    ->toBeAnonymous();
