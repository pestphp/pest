<?php

beforeEach(function () {
    $this->bar = 2;
});

beforeEach(function () {
    $this->bar++;
});

beforeEach(function () {
    $this->bar = 0;
});

it('gets executed before each test', function () {
    expect($this->bar)->toBe(1);

    $this->bar = 'changed';
});

it('gets executed before each test once again', function () {
    expect($this->bar)->toBe(1);
});

beforeEach(function () {
    $this->bar++;
});
