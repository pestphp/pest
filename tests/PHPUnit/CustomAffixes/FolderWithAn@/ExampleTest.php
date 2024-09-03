<?php

declare(strict_types=1);

class MyCustomClassTest extends PHPUnit\Framework\TestCase
{
    public function assertTrueIsTrue()
    {
        $this->assertTrue(true);
    }
}

pest()->extend(MyCustomClassTest::class);

test('custom traits can be used', function () {
    $this->assertTrueIsTrue();
});

test('trait applied in this file')->assertTrueIsTrue();
