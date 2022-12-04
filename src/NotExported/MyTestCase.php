<?php

declare(strict_types=1);

namespace Pest\NotExported;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MyTestCase extends TestCase // @phpstan-ignore-line
{
    public function assertIsTestable(string $testable): void
    {
        static::assertSame(MyTestableClass::class, $testable);
    }
}
