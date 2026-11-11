<?php

declare(strict_types=1);

test('enum is backed by string')
    ->expect('Tests\Fixtures\Arch\ToBeStringBackedEnum\HasStringBacking')
    ->toBeStringBackedEnum();

test('enum is not backed by string')
    ->expect('Tests\Fixtures\Arch\ToBeStringBackedEnum\HasIntBacking')
    ->not->toBeStringBackedEnum();
