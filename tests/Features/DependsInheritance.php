<?php

use PHPUnit\Framework\TestCase;

class InheritanceTest extends TestCase
{
    public function foo()
    {
        return 'bar';
    }
}

uses(InheritanceTest::class);

it('is a test', function () {
    expect(true)->toBeTrue();
});

it('uses correct parent class', function () {
    expect(get_parent_class($this))->toEqual(InheritanceTest::class);
    expect($this->foo())->toEqual('bar');
})->depends('it is a test');
