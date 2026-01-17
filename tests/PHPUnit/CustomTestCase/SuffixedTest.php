<?php

declare(strict_types=1);

namespace Tests\CustomTestCase;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertTrue;

abstract class SuffixedTest extends TestCase
{
    public function assertSuffixedTest()
    {
        assertTrue(true);
    }
}
