<?php

namespace Tests\Fixtures\Inheritance;

class ExampleTest extends Base\ExampleTest
{
    protected $foo;

    public function testExample()
    {
        $this->assertTrue(true);
    }
}
