<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * Called around the test body (`__runTest`). Return `false` to skip the
 * closure — a synthetic assertion is registered so PHPUnit does not flag the
 * test as risky.
 *
 * @internal
 */
interface Runnable
{
    /**
     * @param  string  $filename  Absolute path of the test file.
     * @param  string  $testId    Fully-qualified `Class::method` identifier.
     */
    public function run(string $filename, string $testId): bool;
}
