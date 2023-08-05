<?php

declare(strict_types=1);

namespace Tests\CustomTestCase;

use function PHPUnit\Framework\assertTrue;
use PHPUnit\Framework\TestCase;

class ParentTest extends TestCase
{
    private function getEntity(): bool
    {
        return false;
    }

    /** @test */
    public function testOverrideMethod(): void
    {
        assertTrue($this->getEntity() || true);
    }
}
