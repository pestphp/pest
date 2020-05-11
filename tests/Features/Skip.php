<?php

it('do not skips')
    ->skip(false)
    ->assertTrue(true);

it('skips with truthy')
    ->skip(1)
    ->assertTrue(false);

it('skips with truthy condition by default')
    ->skip()
    ->assertTrue(false);

it('skips with message')
    ->skip('skipped because bar')
    ->assertTrue(false);

it('skips with truthy closure condition')
    ->skip(function () { return '1'; })
    ->assertTrue(false);

it('do not skips with falsy closure condition')
    ->skip(function () { return false; })
    ->assertTrue(true);

it('skips with condition and messsage')
    ->skip(true, 'skipped because foo')
    ->assertTrue(false);
