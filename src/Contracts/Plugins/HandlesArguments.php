<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface HandlesArguments
{
    /**
     * Adds arguments before of the Test Suite execution.
     *
     * @param array<int, string> $argv
     *
     * @return array<int, string>
     */
    public function handleArguments(array $argv): array;
}
