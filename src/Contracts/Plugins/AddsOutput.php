<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface AddsOutput
{
    /**
     * Adds output after the Test Suite execution.
     */
    public function addOutput(int $exitCode): int;
}
