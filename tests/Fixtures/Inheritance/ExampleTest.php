<?php

declare(strict_types=1);

namespace Tests\Fixtures\Inheritance;

class ExampleTest extends Base\ExampleTest
{
    protected $foo;

    #[\Override]
    public function test_example(): void
    {
        expect(true)->toBeTrue();
    }
}
