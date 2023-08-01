<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('missing suffix')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToHaveSuffix\\HasNoSuffix')
    ->toHaveSuffix('Suffix');

test('has suffix')
    ->expect('Tests\\Fixtures\\Arch\\ToHaveSuffix\\HasSuffix')
    ->toHaveSuffix('Suffix');

test('opposite missing suffix')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToHaveSuffix\\HasSuffix')
    ->not->toHaveSuffix('Suffix');

test('opposite has suffix')
    ->expect('Tests\\Fixtures\\Arch\\ToHaveSuffix\\HasNoSuffix')
    ->not->toHaveSuffix('Suffix');
