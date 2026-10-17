<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface FinalizesOutput
{
    public function finalizeOutput(int $exitCode): int;
}
