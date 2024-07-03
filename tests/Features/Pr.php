<?php

beforeEach(function () {
    expect(true)->toBeTrue();
})->pr(1);

it('may be associated with an pr', function () {
    expect(true)->toBeTrue();
})->pr(2);

describe('nested', function () {
    it('may be associated with an pr', function () {
        expect(true)->toBeTrue();
    })->pr('#3');
})->pr(4)->note('an note between an the pr')->pr(['#5', 6]);
