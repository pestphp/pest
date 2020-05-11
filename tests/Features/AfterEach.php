<?php

$state = new stdClass();

beforeEach(function () use ($state) {
    $this->state = $state;
});

afterEach(function () use ($state) {
    $this->state->bar = 2;
});

it('does not get executed before the test', function () {
    assertFalse(property_exists($this->state, 'bar'));
});

it('gets executed after the test', function () {
    assertTrue(property_exists($this->state, 'bar'));
    assertEquals(2, $this->state->bar);
});
