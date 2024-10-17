<?php

declare(strict_types=1);

namespace Pest;

function version(): string
{
    return '3.4.0';
}

function testDirectory(string $file = ''): string
{
    return TestSuite::getInstance()->testPath.DIRECTORY_SEPARATOR.$file;
}

/**
 * Returns array depth.
 *
 * @param  array<mixed>  $array
 */
function getArrayDepth(array $array): int
{
    $depth = 0;

    foreach ($array as $elem) {
        if (is_array($elem)) {
            $depth = getArrayDepth($elem) + 1;
        }
    }

    return $depth;
}
