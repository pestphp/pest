<?php

test('incompleted');

it('is incompleted');

it('is incompleted even with method calls like skip')->skip(false);

it('is incompleted even with method calls like group')->group('wtv');

it('is not incompleted because of expect')->expect(true)->toBeTrue();

it('is not incompleted because of assert')->assertTrue(true);

it('is not incompleted because of test with assertions', function () {
    expect(true)->toBeTrue();
});

describe('a "describe" group of tests', function () {
    it('is incompleted');
});
