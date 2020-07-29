<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass when all keys exist')
    ->expect(['a' => 1, 'b', 'c' => 'world'])
    ->toHaveKeys(['a', 'c'])
    ->group('toHaveKeys');

test('fail when at least one key is missing')
    ->expect(['a' => 1, 'b', 'c' => 'world'])
    ->toHaveKeys(['a', 'hello'])
    ->throws(ExpectationFailedException::class)
    ->group('toHaveKeys');

test('not - pass when all keys are missing')
    ->expect(['a' => 1, 'b', 'c' => 'world'])
    ->not()->toHaveKeys(['hello', 'world'])
    ->group('toHaveKeys');

test('not - fail when all keys exist')
    ->expect(['a' => 1, 'hello' => 'world', 'c'])
    ->not()->toHaveKeys(['a', 'hello'])
    ->throws(ExpectationFailedException::class)
    ->group('toHaveKeys');

test('not - fail when at least one key exists')
    ->expect(['a' => 1, 'b', 'c' => 'world'])
    ->not()->toHaveKeys(['a', 'hello'])
    ->throws(ExpectationFailedException::class)
    ->group('toHaveKeys');
