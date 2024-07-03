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
     * The describe "before each" call.
     */
    private ?BeforeEachCall $currentBeforeEachCall = null;

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
        unset($this->currentBeforeEachCall);

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
    public function __call(string $name, array $arguments): self
    {
        $filename = Backtrace::file();

        if (! $this->currentBeforeEachCall instanceof \Pest\PendingCalls\BeforeEachCall) {
            $this->currentBeforeEachCall = new BeforeEachCall(TestSuite::getInstance(), $filename);

            $this->currentBeforeEachCall->describing = $this->description;
        }

        $this->currentBeforeEachCall->{$name}(...$arguments); // @phpstan-ignore-line

        return $this;
    }
}
