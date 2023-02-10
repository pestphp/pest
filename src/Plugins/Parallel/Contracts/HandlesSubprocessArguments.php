<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Contracts;

interface HandlesSubprocessArguments
{
    public function handleSubprocessArguments(array $arguments): array;
}
