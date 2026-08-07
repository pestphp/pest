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
     * @var array<int, Description>
     */
    private static array $describing = [];

    private ?BeforeEachCall $currentBeforeEachCall = null;

    public function __construct(
        public readonly TestSuite $testSuite,
        public readonly string $filename,
        public readonly Description $description,
        public readonly Closure $tests
    ) {
        //
    }

    /**
     * @return array<int, Description>
     */
    public static function describing(): array
    {
        return self::$describing;
    }

    public function __destruct()
    {
        $beforeEach = $this->currentBeforeEachCall;
        $this->currentBeforeEachCall = null;
        unset($beforeEach);
        self::$describing[] = $this->description;

        try {
            ($this->tests)();
        } finally {
            array_pop(self::$describing);
        }
    }

    /**
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
