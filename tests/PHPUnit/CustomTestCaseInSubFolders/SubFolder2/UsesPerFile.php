<?php

use PHPUnit\Framework\TestCase;

trait MyCustomTrait
{
    public function assertFalseIsFalse()
    {
        assertFalse(false);
    }
}

abstract class MyCustomClass extends TestCase
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
