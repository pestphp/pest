<?php

test('class has constructor')
    ->expect('Tests\Fixtures\Arch\ToHaveConstructor\HasConstructor\HasConstructor')
    ->toHaveConstructor();

test('class has no constructor')
    ->expect('Tests\Fixtures\Arch\ToHaveConstructor\HasNoConstructor\HasNoConstructor')
    ->not->toHaveConstructor();
