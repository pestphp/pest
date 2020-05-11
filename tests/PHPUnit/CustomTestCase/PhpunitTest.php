<?php

declare(strict_types=1);

namespace Tests\CustomTestCase;

use function PHPUnit\Framework\assertTrue;
use PHPUnit\Framework\TestCase;

class PhpunitTest extends TestCase
{
    public static $executed = false;

    /** @test */
    public function testThatGetsExecuted(): void
    {
        self::$executed = true;

        $this->assertTrue(true);
    }
}

// register_shutdown_function(fn () => assertTrue(PhpunitTest::$executed));
