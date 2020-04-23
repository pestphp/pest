<?php

interface Foo
{
    public function bar(): int;
}

it('has bar', function () {
    $mock = Mockery::mock(Foo::class);
    $mock->shouldReceive('bar')
        ->times(1)
        ->andReturn(2);

    assertEquals(2, $mock->bar());
});
