<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('class has attribute')
    ->expect('Tests\\Fixtures\\Arch\\ToHaveAttribute\\HaveAttribute')
    ->toHaveAttribute('Tests\\Fixtures\\Arch\\ToHaveAttribute\\Attributes\\AsAttribute');

test('opposite class has attribute')
    ->throws(ArchExpectationFailedException::class)
    ->expect('Tests\\Fixtures\\Arch\\ToHaveAttribute\\HaveAttribute')
    ->not
    ->toHaveAttribute('Tests\\Fixtures\\Arch\\ToHaveAttribute\\Attributes\\AsAttribute');

test('class not has attribute')
    ->expect('Tests\\Fixtures\\Arch\\ToHaveAttribute\\NotHaveAttribute')
    ->not
    ->toHaveAttribute('Tests\\Fixtures\\Arch\\ToHaveAttribute\\Attributes\\AsAttribute');
