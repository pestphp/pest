<?php

trait MyCustomTrait
{
    public function assertFalseIsFalse()
    {
        assertFalse(false);
    }
}

class MyCustomClass extends PHPUnit\Framework\TestCase
{
    public function assertTrueIsTrue()
    {
        assertTrue(true);
    }
}

uses(MyCustomClass::class, MyCustomTrait::class);

test('custom traits can be used', function () {
    $this->assertTrueIsTrue();
});

test('trait applied in this file')->assertTrueIsTrue();
