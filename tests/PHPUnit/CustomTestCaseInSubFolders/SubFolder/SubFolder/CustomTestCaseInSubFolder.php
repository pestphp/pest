<?php

declare(strict_types=1);

namespace Tests\CustomTestCaseInSubFolders\SubFolder\SubFolder;

use PHPUnit\Framework\TestCase;

class CustomTestCaseInSubFolder extends TestCase
{
    public function assertCustomInSubFolderTrue()
    {
        $this->assertTrue(true);
    }
}
