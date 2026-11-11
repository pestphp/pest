<?php

use Tests\Fixtures\Arch\ToHaveDestructor\HasDestructor\HasDestructor;
use Tests\Fixtures\Arch\ToHaveDestructor\HasNoDestructor\HasNoDestructor;

test('class has destructor')
    ->expect(HasDestructor::class)
    ->toHaveDestructor();

test('class has no destructor')
    ->expect(HasNoDestructor::class)
    ->not->toHaveDestructor();
