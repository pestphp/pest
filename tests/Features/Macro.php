<?php

use Illuminate\Support\Traits\Macroable;
use PHPUnit\Framework\TestCase;

uses(Macroable::class);

it('can call chained macro method')->macro('bar', function () {
    assertInstanceOf(TestCase::class, $this);

    return $this;
})->bar();
