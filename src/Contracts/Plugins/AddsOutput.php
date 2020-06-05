<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface AddsOutput
{
    /**
     * Allows to add custom output after the test suite was executed.
     */
    public function addOutput(int $testReturnCode): int;
}
