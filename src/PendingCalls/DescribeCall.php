<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
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
     * Creates a new Pending Call.
     */
    public function __construct(
        public readonly TestSuite $testSuite,
        public readonly string $filename,
        public readonly Description $description,
        public readonly Closure $tests
    ) {
        //
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
        // Ensure BeforeEachCall destructs before creating tests
        // by moving to local scope and clearing the reference
        $beforeEach = $this->currentBeforeEachCall;
        $this->currentBeforeEachCall = null;
        unset($beforeEach);  // Trigger destructor immediately

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
        if (! $this->currentBeforeEachCall instanceof BeforeEachCall) {
            $this->currentBeforeEachCall = new BeforeEachCall(TestSuite::getInstance(), $this->filename);

            $this->currentBeforeEachCall->describing = array_merge(
                DescribeCall::describing(),
                [$this->description]
            );
        }

        $this->currentBeforeEachCall->{$name}(...$arguments);

        return $this;
    }
}
