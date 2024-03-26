<?php

test('enum is backed by int')
    ->expect('Tests\Fixtures\Arch\ToBeIntBackedEnum\HasIntBacking')
    ->toBeIntBackedEnum();

test('enum is not backed by int')
    ->expect('Tests\Fixtures\Arch\ToBeIntBackedEnum\HasStringBacking')
    ->not->toBeIntBackedEnum();
