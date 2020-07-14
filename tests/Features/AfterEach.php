<?php

$state = new stdClass();

beforeEach(function () use ($state) {
    $this->state = $state;
});

afterEach(function () use ($state) {
    $this->state->bar = 2;
});

it('does not get executed before the test', function () {
    expect(property_exists($this->state, 'bar'))->toBeFalse();
});

it('gets executed after the test', function () {
    expect(property_exists($this->state, 'bar'))->toBeTrue();
    expect($this->state->bar)->toBe(2);
});
