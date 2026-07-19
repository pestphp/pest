<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;

class MyCustomClassTest extends TestCase
{
    public function assertTrueIsTrue(): void
    {
        expect(true)->toBeTrue();
    }
}

pest()->extend(MyCustomClassTest::class);

test('custom traits can be used', function (): void {
    $this->assertTrueIsTrue();
});

test('trait applied in this file')->assertTrueIsTrue();
