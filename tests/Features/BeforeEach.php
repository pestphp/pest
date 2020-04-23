<?php

beforeEach(function () {
    $this->bar = 2;
});

it('gets executed before each test', function () {
    assertEquals($this->bar, 2);

    $this->bar = 'changed';
});

it('gets executed before each test once again', function () {
    assertEquals($this->bar, 2);
});
