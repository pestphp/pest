<?php

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Contracts\HandlesSubprocessArguments;
use Pest\TestCaseMethodFilters\TodoTestCaseFilter;
use Pest\TestSuite;

final class Pest implements HandlesSubprocessArguments
{
    use HandleArguments;

    public function handleSubprocessArguments(array $arguments): array
    {
        $_SERVER['PEST_PARALLEL'] = '1';

        return $arguments;
    }
}
