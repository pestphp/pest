<?php

trait MyCustomTrait
{
    public function assertFalseIsFalse()
    {
        assertFalse(false);
    }
}

abstract class MyCustomClass extends PHPUnit\Framework\TestCase
{
    public function assertTrueIsTrue()
    {
        $this->assertTrue(true);
    }
}

pest()->extend(MyCustomClass::class)->use(MyCustomTrait::class);

test('custom traits can be used', function () {
    $this->assertTrueIsTrue();
});

test('trait applied in this file')->assertTrueIsTrue();
