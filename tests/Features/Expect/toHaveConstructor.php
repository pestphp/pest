<?php

use Tests\Fixtures\Arch\ToHaveConstructor\HasConstructor\HasConstructor;
use Tests\Fixtures\Arch\ToHaveConstructor\HasNoConstructor\HasNoConstructor;

test('class has constructor')
    ->expect(HasConstructor::class)
    ->toHaveConstructor();

test('class has no constructor')
    ->expect(HasNoConstructor::class)
    ->not->toHaveConstructor();
