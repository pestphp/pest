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
    ->skip(function () {
        return '1';
    })
    ->assertTrue(false);

it('do not skips with falsy closure condition')
    ->skip(function () {
        return false;
    })
    ->assertTrue(true);

it('skips with condition and message')
    ->skip(true, 'skipped because foo')
    ->assertTrue(false);

it('skips when skip after assertion')
    ->assertTrue(true)
    ->skip();

it('skips if the PHP version is the current one (with message)')
    ->skipForVersion(PHP_VERSION, null, 'because of the current version')
    ->assertTrue(true);

it('skips if the PHP version is greater that 7 (should skip)')
    ->skipForVersion('7', '>')
    ->assertTrue(true);

it('skips if the PHP version is less than 7 (should never skip)')
    ->skipForVersion('7', '<')
    ->assertTrue(true);

it('runs only if the PHP version is the current one (should never skip)')
    ->onlyForVersion(PHP_VERSION)
    ->assertTrue(true);
