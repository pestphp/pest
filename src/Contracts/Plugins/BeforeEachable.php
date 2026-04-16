<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

use Pest\Plugins\Tia\CachedTestResult;

/**
 * Plugins implementing this interface are consulted before each test's
 * `setUp()`. The return value controls what happens:
 *
 *   - `null`              → test proceeds normally.
 *   - `CachedTestResult`  → test replays the cached status. For non-success
 *                           statuses the appropriate exception is thrown
 *                           from `setUp` (PHPUnit handles it natively). For
 *                           success, a synthetic assertion is registered and
 *                           the body + tearDown are skipped via a flag.
 *
 * @internal
 */
interface BeforeEachable
{
    public function beforeEach(string $filename, string $testId): ?CachedTestResult;
}
