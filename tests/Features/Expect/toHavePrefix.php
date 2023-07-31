<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('missing prefix')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToHavePrefix\\HasNoPrefix')
    ->toHavePrefix('Prefix');

test('has prefix')
    ->expect('Tests\\Fixtures\\Arch\\ToHavePrefix\\HasPrefix')
    ->toHavePrefix('Prefix');

test('opposite missing prefix')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToHavePrefix\\HasPrefix')
    ->not->toHavePrefix('Prefix');

test('opposite has prefix')
    ->expect('Tests\\Fixtures\\Arch\\ToHavePrefix\\HasNoPrefix')
    ->not->toHavePrefix('Prefix');
