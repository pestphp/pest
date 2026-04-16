<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * Plugins implementing this interface are called before each test's `setUp`.
 *
 * Return `false` to skip the test entirely — `setUp`, body and `tearDown`
 * are all bypassed and the test counts as passed with one synthetic
 * assertion. Any other return value lets the test proceed normally.
 *
 * Resolution happens once per process via `Loader::getPlugins`; the per-test
 * call is a cheap iteration over the cached list.
 *
 * @internal
 */
interface BeforeEachable
{
    /**
     * @param  string  $filename  Absolute path of the test file.
     * @param  string  $testId    Fully-qualified `Class::method` identifier.
     */
    public function beforeEach(string $filename, string $testId): bool;
}
