<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Contracts;

interface HandlersWorkerArguments
{
    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function handleWorkerArguments(array $arguments): array;
}
