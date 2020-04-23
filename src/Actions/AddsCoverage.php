<?php

declare(strict_types=1);

namespace Pest\Actions;

use Pest\Console\Coverage;
use Pest\TestSuite;

/**
 * @internal
 */
final class AddsCoverage
{
    /**
     * If any, adds the coverage params to the given arguments.
     *
     * @param array<int, string> $arguments
     *
     * @return array<int, string>
     */
    public static function from(TestSuite $testSuite, array $arguments): array
    {
        if (in_array('--coverage', $arguments, true)) {
            $testSuite->coverage = true;

            $arguments = array_flip($arguments);
            unset($arguments['--coverage']);

            $arguments   = array_flip($arguments);
            $arguments[] = '--coverage-php';
            $arguments[] = Coverage::getPath();
        }

        return $arguments;
    }
}
