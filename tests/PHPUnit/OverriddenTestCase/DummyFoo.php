<?php

namespace Tests\PHPUnit\OverriddenTestCase;

class DummyFoo
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}
