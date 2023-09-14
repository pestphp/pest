<?php

declare(strict_types=1);

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToUseTrait\TraitOne;
use Tests\Fixtures\Arch\ToUseTrait\TraitTwo;

describe('toUseTrait', function () {
    test('class uses trait')
        ->expect('Tests\\Fixtures\\Arch\\ToUseTrait\\UsesTraitOne')
        ->toUseTrait(TraitOne::class);

    test('opposite class does not use trait')
        ->throws(ArchExpectationFailedException::class)
        ->expect('Tests\\Fixtures\\Arch\\ToUseTrait\\UsesTraitOne')
        ->not->toUseTrait(TraitOne::class);

    test('class uses a trait via a parent class')
        ->expect('Tests\\Fixtures\\Arch\\ToUseTrait\\UsesTraitOneViaParent')
        ->toUseTrait(TraitOne::class);

    test('class uses a trait via a parents parent class')
        ->expect('Tests\\Fixtures\\Arch\\ToUseTrait\\UsesTraitOneViaParentsParent')
        ->toUseTrait(TraitOne::class);

    test('class uses multiple traits')
        ->expect('Tests\\Fixtures\\Arch\\ToUseTrait\\UsesTraitOneAndTwo')
        ->toUseTrait(TraitOne::class)
        ->toUseTrait(TraitTwo::class);

    test('check class uses multiple traits in array format')
        ->expect('Tests\\Fixtures\\Arch\\ToUseTrait\\UsesTraitOneAndTwo')
        ->toUseTrait([
            TraitOne::class,
            TraitTwo::class,
        ]);

    test('failure when the class does not use a trait')
        ->throws(ArchExpectationFailedException::class)
        ->expect('Tests\\Fixtures\\Arch\\ToUseTrait\\UsesTraitOne')
        ->toUseTrait(TraitTwo::class);

    test('class does not use a trait')
        ->expect('Tests\\Fixtures\\Arch\\ToUseTrait\\UsesTraitOne')
        ->not->toUseTrait(TraitTwo::class);
});
