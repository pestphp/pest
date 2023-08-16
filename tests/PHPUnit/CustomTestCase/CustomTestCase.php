<?php

declare(strict_types=1);

namespace Tests\CustomTestCase;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertTrue;

abstract class CustomTestCase extends TestCase
{
    public function assertCustomTrue()
    {
        assertTrue(true);
    }
}
