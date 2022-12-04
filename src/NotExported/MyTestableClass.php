<?php

declare(strict_types=1);

namespace Pest\NotExported;

/**
 * @internal
 */
final class MyTestableClass
{
    public function foo(): void
    {
        // ...
    }
}

it('foo', function () {
    $testable = new MyTestableClass();

    $this->assertIsTestable(get_class($testable)); // @phpstan-ignore-line
});
