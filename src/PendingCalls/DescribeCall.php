<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use Pest\Support\Backtrace;
use Pest\TestSuite;

/**
 * @internal
 */
final class DescribeCall
{
    /**
     * The current describe call.
     */
    private static ?string $describing = null;

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

    /**
     * What is the current describing.
     */
    public static function describing(): ?string
    {
        return self::$describing;
    }

    /**
     * Creates the Call.
     */
    public function __destruct()
    {
        self::$describing = $this->description;

        try {
            ($this->tests)();
        } finally {
            self::$describing = null;
        }
    }

    /**
     * Dynamically calls methods on each test call.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): BeforeEachCall
    {
        $filename = Backtrace::file();

        $beforeEachCall = new BeforeEachCall(TestSuite::getInstance(), $filename);

        $beforeEachCall->describing = $this->description;

        return $beforeEachCall->{$name}(...$arguments); // @phpstan-ignore-line
    }
}
