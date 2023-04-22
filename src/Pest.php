<?php

declare(strict_types=1);

namespace Pest;

function version(): string
{
    return '2.5.3';
}

function testDirectory(string $file = ''): string
{
    return TestSuite::getInstance()->testPath.'/'.$file;
}
