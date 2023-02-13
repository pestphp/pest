<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Contracts\HandlersWorkerArguments;

final class Pest implements HandlersWorkerArguments
{
    use HandleArguments;

    /**
     * Handles the arguments, adding the "PEST_PARALLEL" environment variable to the global $_SERVER.
     */
    public function handleWorkerArguments(array $arguments): array
    {
        $_SERVER['PEST_PARALLEL'] = '1';

        return $arguments;
    }
}
