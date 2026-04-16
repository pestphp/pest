<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Immutable snapshot of a previous test run's outcome. Stored in the TIA
 * graph and returned by `BeforeEachable::beforeEach` so `Testable` can
 * faithfully replay the exact status — pass, fail, skip, todo, incomplete,
 * risky, etc. — without executing the test body.
 *
 * @internal
 */
final readonly class CachedTestResult
{
    /**
     * PHPUnit TestStatus int constants:
     *   0 = success, 1 = skipped, 2 = incomplete,
     *   3 = notice, 4 = deprecation, 5 = risky,
     *   6 = warning, 7 = failure, 8 = error.
     */
    public function __construct(
        public int $status,
        public string $message = '',
        public float $time = 0.0,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === 0;
    }
}
