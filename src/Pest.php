<?php

declare(strict_types=1);

namespace Pest;

function version(): string
{
    return '5.0.0-rc.7';
}

function testDirectory(string $file = ''): string
{
    return TestSuite::getInstance()->testPath.DIRECTORY_SEPARATOR.$file;
}
