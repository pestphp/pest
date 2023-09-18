<?php

test('class has destructor')
    ->expect('Tests\Fixtures\Arch\ToHaveDestructor\HasDestructor\HasDestructor')
    ->toHaveDestructor();

test('class has no destructor')
    ->expect('Tests\Fixtures\Arch\ToHaveDestructor\HasNoDestructor\HasNoDestructor')
    ->not->toHaveDestructor();
