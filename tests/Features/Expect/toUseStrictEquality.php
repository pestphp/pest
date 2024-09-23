<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('missing strict equality')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToUseStrictEquality\\NotStrictEquality')
    ->toUseStrictEquality();

test('has strict equality')
    ->expect('Tests\\Fixtures\\Arch\\ToUseStrictEquality\\StrictEquality')
    ->toUseStrictEquality();

test('opposite missing strict equality')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToUseStrictEquality\\StrictEquality')
    ->not->toUseStrictEquality();

test('opposite has strict equality')
    ->expect('Tests\\Fixtures\\Arch\\ToUseStrictEquality\\NotStrictEquality')
    ->not->toUseStrictEquality();
