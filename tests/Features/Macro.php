<?php

use Illuminate\Support\Traits\Macroable;
use PHPUnit\Framework\TestCase;

uses(Macroable::class);

beforeEach(function () {
    $this->macro('bar', function () {
        assertInstanceOf(TestCase::class, $this);
    });
});

it('can call chained macro method')->bar();

it('will throw exception from call if no macro exists')
    ->throws(BadMethodCallException::class)
    ->foo();
