<?php

beforeEach(function () {
    expect(true)->toBeTrue();
})->assignee('nunomaduro');

it('may be associated with an assignee', function () {
    expect(true)->toBeTrue();
})->assignee('taylorotwell');

describe('nested', function () {
    it('may be associated with an assignee', function () {
        expect(true)->toBeTrue();
    })->assignee('taylorotwell');
})->assignee('nunomaduro')->note('an note between an the assignee')->assignee(['jamesbrooks', 'joedixon']);
