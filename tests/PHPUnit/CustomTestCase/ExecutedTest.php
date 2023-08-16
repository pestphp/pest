<?php

declare(strict_types=1);

namespace Tests\CustomTestCase;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertTrue;

class ExecutedTest extends TestCase
{
    public static $executed = false;

    /** @test */
    public function testThatGetsExecuted(): void
    {
        self::$executed = true;

        assertTrue(true);
    }
}

// register_shutdown_function(fn () => assertTrue(ExecutedTest::$executed));
