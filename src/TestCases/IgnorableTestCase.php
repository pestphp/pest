<?php

declare(strict_types=1);

namespace Pest\TestCases;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @phpstan-ignore-next-line
 */
class IgnorableTestCase extends TestCase
{
    /**
     * @test
     */
    public function fake(): void
    {
        self::markTestIncomplete();
    }
}
