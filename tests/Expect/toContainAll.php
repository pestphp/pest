<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass when all values exist')
    ->expect([1, 2, 42])
    ->toContainAll([42, 2])
    ->group('toContainAll');

test('pass when all strings exist')
    ->expect('Pest is awesome')
    ->toContainAll(['Pest', 'awesome'])
    ->group('toContainAll');

test('fail when at least one value is missing')
    ->expect([1, 2, 42])
    ->toContainAll([42, 14])
    ->throws(ExpectationFailedException::class)
    ->group('toContainAll');

test('fail when at least one string is missing')
    ->expect('Pest is awesome')
    ->toContainAll(['Laravel', 'awesome'])
    ->throws(ExpectationFailedException::class)
    ->group('toContainAll');

test('not - pass when all values are missing')
    ->expect([1, 2, 42])
    ->not()->toContainAll([3, 14])
    ->group('toContainAll');

test('not - pass when all strings are missing')
    ->expect('Pest is awesome')
    ->not()->toContainAll(['Laravel', 'clever'])
    ->group('toContainAll');

test('not - fail when all values exist')
    ->expect([1, 2, 42])
    ->not()->toContainAll([2, 42])
    ->throws(ExpectationFailedException::class)
    ->group('toContainAll');

test('not - fail when all strings exist')
    ->expect('Pest is awesome')
    ->not()->toContainAll(['Pest', 'awesome'])
    ->throws(ExpectationFailedException::class)
    ->group('toContainAll');

test('not - fail when at least one value exists')
    ->expect([1, 2, 42])
    ->not()->toContainAll([3, 42])
    ->throws(ExpectationFailedException::class)
    ->group('toContainAll');

test('not - fail when at least one string exists')
    ->expect('Pest is awesome')
    ->not()->toContainAll(['Pest', 'clever'])
    ->throws(ExpectationFailedException::class)
    ->group('toContainAll');
