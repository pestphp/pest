<?php

declare(strict_types=1);

namespace Pest;

function version(): string
{
    return '3.0.8';
}

function testDirectory(string $file = ''): string
{
    return TestSuite::getInstance()->testPath.DIRECTORY_SEPARATOR.$file;
}
