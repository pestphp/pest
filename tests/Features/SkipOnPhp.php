<?php

it('can run on php version')
    ->skipOnPhp('<=7.4.0')
    ->assertTrue(true);

it('can skip on specific php version')
    ->skipOnPhp(PHP_VERSION)
    ->assertTrue(false);

it('can run on specific php version')
    ->skipOnPhp('7.4.0')
    ->assertTrue(true);

it('can skip on php versions depending on constraint')
    ->skipOnPhp('>=7.4.0')
    ->assertTrue(false);
