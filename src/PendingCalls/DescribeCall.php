<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use Pest\PendingCalls;
use Pest\TestSuite;

/**
 * @internal
 */
final class DescribeCall
{
    /**
     * Creates a new Pending Call.
     */
    public function __construct(
        public readonly TestSuite $testSuite,
        public readonly string $filename,
        public readonly string $description,
        public readonly Closure $tests
    ) {
        //
    }

    public function __destruct()
    {
        PendingCalls::endDescribe($this);
    }

    /**
     * Dynamically calls methods on each test call.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        foreach (PendingCalls::$testCalls as [$testCall]) {
            $testCall->{$name}(...$arguments); // @phpstan-ignore-line
        }

        return $this;
    }
}
