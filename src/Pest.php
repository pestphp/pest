<?php

declare(strict_types=1);

namespace Pest;

function version(): string
{
    return '1.21.3';
}

function testDirectory(string $file = ''): string
{
    return TestSuite::getInstance()->testPath . '/' . $file;
}
