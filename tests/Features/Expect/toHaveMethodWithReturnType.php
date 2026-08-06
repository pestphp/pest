<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('class has method with return type')
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasMethodWithReturnType\HasMethodWithReturnType')
    ->toHaveMethodWithReturnType('foo', 'string');

test('opposite class has method with return type')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasMethodWithReturnType\HasMethodWithReturnType')
    ->not->toHaveMethodWithReturnType('foo', 'string');

test('failure when return type does not match')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\WrongReturnType\WrongReturnType')
    ->toHaveMethodWithReturnType('foo', 'string');

test('opposite passes when return type does not match')
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\WrongReturnType\WrongReturnType')
    ->not->toHaveMethodWithReturnType('foo', 'string');

test('failure when method has no return type')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasNoReturnType\HasNoReturnType')
    ->toHaveMethodWithReturnType('foo', 'string');

test('class has method with union return type exact match')
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasUnionReturnType\HasUnionReturnType')
    ->toHaveMethodWithReturnType('foo', 'string|int');

test('failure when exact union does not match')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasUnionReturnType\HasUnionReturnType')
    ->toHaveMethodWithReturnType('foo', 'string');

test('class has method with union return type containing types')
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasUnionReturnType\HasUnionReturnType')
    ->toHaveMethodWithReturnType('foo', ['string', 'int']);

test('class has method with partial union match')
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasPartialUnionMatch\HasPartialUnionMatch')
    ->toHaveMethodWithReturnType('foo', ['string', 'int']);

test('failure when not all array types are present')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasUnionReturnType\HasUnionReturnType')
    ->toHaveMethodWithReturnType('foo', ['string', 'float']);

test('opposite passes when not all array types are present')
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasUnionReturnType\HasUnionReturnType')
    ->not->toHaveMethodWithReturnType('foo', ['string', 'float']);

test('failure when class has no method')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasNoMethod\HasNoMethodClass')
    ->toHaveMethodWithReturnType('foo', 'string');

test('opposite passes when class has no method')
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasNoMethod\HasNoMethodClass')
    ->not->toHaveMethodWithReturnType('foo', 'string');

test('class has method with nullable return type containing types')
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasNullableReturnType\HasNullableReturnType')
    ->toHaveMethodWithReturnType('foo', ['array', 'null']);

test('class has method with nullable return type exact match')
    ->expect('Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasNullableReturnType\HasNullableReturnType')
    ->toHaveMethodWithReturnType('foo', '?array');
