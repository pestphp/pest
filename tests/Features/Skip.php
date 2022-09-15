<?php

beforeEach(function () {
    $this->shouldSkip = true;
});

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

it('can use something in the test case as a condition')
    ->skip(function () {
        return $this->shouldSkip;
    }, 'This test was skipped')
    ->assertTrue(false);

it('can user higher order callables and skip')
    ->skip(function () {
        return $this->shouldSkip;
    })
    ->expect(function () {
        return $this->shouldSkip;
    })
    ->toBeFalse();
