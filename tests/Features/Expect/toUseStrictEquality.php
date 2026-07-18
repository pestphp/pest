<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToUseStrictEquality\NotStrictEquality;
use Tests\Fixtures\Arch\ToUseStrictEquality\StrictEquality;

test('missing strict equality')
    ->throws(ArchExpectationFailedException::class)
    ->expect(NotStrictEquality::class)
    ->toUseStrictEquality();

test('has strict equality')
    ->expect(StrictEquality::class)
    ->toUseStrictEquality();

test('opposite missing strict equality')
    ->throws(ArchExpectationFailedException::class)
    ->expect(StrictEquality::class)
    ->not->toUseStrictEquality();

test('opposite has strict equality')
    ->expect(NotStrictEquality::class)
    ->not->toUseStrictEquality();
