<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;

class MyCustomClassTest extends TestCase
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
