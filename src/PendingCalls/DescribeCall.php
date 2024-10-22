<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use Pest\Support\Backtrace;
use Pest\Support\Description;
use Pest\TestSuite;

/**
 * @internal
 */
final class DescribeCall
{
    /**
     * The current describe call.
     *
     * @var array<int, Description>
     */
    private static array $describing = [];

    /**
     * The describe "before each" call.
     */
    private ?BeforeEachCall $currentBeforeEachCall = null;

    /**
     * The unique description for this describe block
     */
    private readonly Description $description;

    /**
     * Creates a new Pending Call.
     */
    public function __construct(
        public readonly TestSuite $testSuite,
        public readonly string $filename,
        string $description,
        public readonly Closure $tests
    ) {
        $this->description = new Description($description);
    }

    /**
     * What is the current describing.
     *
     * @return array<int, Description>
     */
    public static function describing(): array
    {
        return self::$describing;
    }

    /**
     * Creates the Call.
     */
    public function __destruct()
    {
        unset($this->currentBeforeEachCall);

        self::$describing[] = $this->description;

        try {
            ($this->tests)();
        } finally {
            array_pop(self::$describing);
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

            $this->currentBeforeEachCall->describing[] = $this->description;
        }

        $this->currentBeforeEachCall->{$name}(...$arguments); // @phpstan-ignore-line

        return $this;
    }
}
