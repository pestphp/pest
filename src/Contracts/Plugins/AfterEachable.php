<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * Called at the start of `tearDown`. Return `false` to skip the framework
 * tearDown, afterEach chain, and method-level cleanup.
 *
 * @internal
 */
interface AfterEachable
{
    /**
     * @param  string  $filename  Absolute path of the test file.
     * @param  string  $testId    Fully-qualified `Class::method` identifier.
     */
    public function afterEach(string $filename, string $testId): bool;
}
