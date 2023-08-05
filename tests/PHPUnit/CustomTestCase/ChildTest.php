<?php

declare(strict_types=1);

namespace Tests\CustomTestCase;

class ChildTest extends ParentTest
{
    private function getEntity(): bool
    {
        return true;
    }
}
