<?php

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Contracts\HandlesSubprocessArguments;
use Pest\Plugins\Retry;

final class Pest implements HandlesArguments, HandlesSubprocessArguments
{
    use HandleArguments;

    public function handleArguments(array $arguments): array
    {
        if (Retry::$retrying) {
            $_ENV['PEST_RETRY'] = '1';
        }

        return $arguments;
    }

    public function handleSubprocessArguments(array $arguments): array
    {
        $_SERVER['PEST_PARALLEL'] = '1';

        if (isset($_SERVER['PEST_RETRY'])) {
            Retry::$retrying = true;
        }

        return $arguments;
    }
}
