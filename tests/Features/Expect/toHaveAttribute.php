<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToHaveAttribute\Attributes\AsAttribute;

test('class has attribute')
    ->expect('Tests\\Fixtures\\Arch\\ToHaveAttribute\\HaveAttribute')
    ->toHaveAttribute(AsAttribute::class);

test('opposite class has attribute')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToHaveAttribute\\HaveAttribute')
    ->not
    ->toHaveAttribute(AsAttribute::class);

test('class not has attribute')
    ->expect('Tests\\Fixtures\\Arch\\ToHaveAttribute\\NotHaveAttribute')
    ->not
    ->toHaveAttribute(AsAttribute::class);
