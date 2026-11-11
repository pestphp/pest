<?php

use PHPUnit\Framework\TestCase;

class InheritanceTest extends TestCase
{
    public function foo(): string
    {
        return 'bar';
    }
}

pest()->extend(InheritanceTest::class);

it('is a test', function (): void {
    expect(true)->toBeTrue();
});

it('uses correct parent class', function (): void {
    expect(get_parent_class($this))->toEqual(InheritanceTest::class)
        ->and($this->foo())->toEqual('bar');
})->depends('it is a test');
