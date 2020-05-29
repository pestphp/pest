<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

use Pest\TestSuite;

/**
 * @internal
 */
interface HandlesArguments
{
    /**
     * Allows to handle custom command line arguments.
     *
     * PLEASE NOTE: it is necessary to remove any custom argument from the array
     * because otherwise the application will complain about them
     *
     * @param array<int, string> $arguments
     *
     * @return array<int, string> the updated list of arguments
     */
    public function handleArguments(TestSuite $testSuite, array $arguments): array;
}
