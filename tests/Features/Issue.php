<?php

beforeEach(function () {
    expect(true)->toBeTrue();
})->issue(1);

it('may be associated with an issue', function () {
    expect(true)->toBeTrue();
})->issue(2);

describe('nested', function () {
    it('may be associated with an issue', function () {
        expect(true)->toBeTrue();
    })->issue('#3');
})->issue(4)->note('an note between an the issue')->issue([5, 6]);
