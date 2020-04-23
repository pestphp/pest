<?php

declare(strict_types=1);

namespace Tests\SubFolder\SubFolder\SubFolder;

use PHPUnit\Framework\TestCase;

class CustomTestCaseInSubFolder extends TestCase
{
    public function assertCustomInSubFolderTrue()
    {
        assertTrue(true);
    }
}
