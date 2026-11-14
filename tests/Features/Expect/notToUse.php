<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use Tests\Fixtures\Arch\NotToUse\AlsoClean;
use Tests\Fixtures\Arch\NotToUse\Clean;
use Tests\Fixtures\Arch\NotToUse\Dependency;
use Tests\Fixtures\Arch\NotToUse\OtherDependency;
use Tests\Fixtures\Arch\NotToUse\UsesDependency;

test('single target negation fails when the target uses the dependency')
    ->throws(ExpectationFailedException::class)
    ->expect(UsesDependency::class)
    ->not->toUse(Dependency::class);

test('single target negation passes when the target does not use the dependency')
    ->expect(Clean::class)
    ->not->toUse(Dependency::class);

test('multi target negation fails when any target uses the dependency')
    ->throws(ExpectationFailedException::class)
    ->expect([UsesDependency::class, Clean::class])
    ->not->toUse(Dependency::class);

test('multi target negation passes when no target uses the dependency')
    ->expect([Clean::class, AlsoClean::class])
    ->not->toUse(Dependency::class);

test('multi target negation fails when any target uses any listed dependency')
    ->throws(ExpectationFailedException::class)
    ->expect([UsesDependency::class, Clean::class])
    ->not->toUse([Dependency::class, OtherDependency::class]);

test('positive to use still fails when one target skips the dependency')
    ->throws(ExpectationFailedException::class)
    ->expect([UsesDependency::class, Clean::class])
    ->toUse(Dependency::class);
