<?php

declare(strict_types=1);

namespace Tests\CustomTestCase;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertTrue;

class ExecutedTest extends TestCase
{
    public static $executed = false;

    #[Test]
    public function test_that_gets_executed(): void
    {
        self::$executed = true;

        assertTrue(true);
    }
}
