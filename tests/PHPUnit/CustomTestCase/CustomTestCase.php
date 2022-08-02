<?php

declare(strict_types=1);

namespace Tests\CustomTestCase;

use function PHPUnit\Framework\assertTrue;

use PHPUnit\Framework\TestCase;

abstract class CustomTestCase extends TestCase
{
    public function assertCustomTrue()
    {
        assertTrue(true);
    }
}
