<?php

declare(strict_types=1);

namespace Pest;

function version(): string
{
    return '1.3.2';
}

function testDirectory(string $file = ''): string
{
    return TestSuite::getInstance()->testPath . '/' . $file;
}
