<?php

use PHPUnit\Framework\TestCase;

trait MyCustomTrait
{
    public function assertFalseIsFalse(): void
    {
        assertFalse(false);
    }
}

abstract class MyCustomClass extends TestCase
{
    public function assertTrueIsTrue(): void
    {
        expect(true)->toBeTrue();
    }
}

pest()->extend(MyCustomClass::class)->use(MyCustomTrait::class);

test('custom traits can be used', function (): void {
    $this->assertTrueIsTrue();
});

test('trait applied in this file')->assertTrueIsTrue();
