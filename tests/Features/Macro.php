<?php

use Illuminate\Support\Traits\Macroable;
use PHPUnit\Framework\TestCase;

uses(Macroable::class);

beforeEach()->macro('bar', function () {
    expect($this)->toBeInstanceOf(TestCase::class);
});

it('can call chained macro method')->bar();

it('will throw exception from call if no macro exists')
    ->throws(BadMethodCallException::class)
    ->foo();
