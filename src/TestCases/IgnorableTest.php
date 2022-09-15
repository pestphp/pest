<?php

declare(strict_types=1);

namespace Pest\TestCases;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class IgnorableTest extends TestCase
{
    /**
     * Creates a dummy assertion.
     */
    public function testIgnorable(): void
    {
        self::assertTrue(true);
    }
}
