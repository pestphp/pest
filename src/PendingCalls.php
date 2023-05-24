<?php

declare(strict_types=1);

namespace Pest;

use Closure;
use Pest\PendingCalls\AfterEachCall;
use Pest\PendingCalls\BeforeEachCall;
use Pest\PendingCalls\DescribeCall;
use Pest\PendingCalls\TestCall;

/**
 * @internal
 */
final class PendingCalls
{
    /**
     * The current describe call.
     */
    public static ?string $describing = null;

    /**
     * The list of before each pending calls.
     *
     * @var array<int, BeforeEachCall>
     */
    public static array $beforeEachCalls = [];

    /**
     * The list of test pending calls.
     *
     * @var array<int, array{0: TestCall, 1: Closure}>
     */
    public static array $testCalls = [];

    /**
     * The list of after each pending calls.
     *
     * @var array<int, array{0: AfterEachCall, 1: Closure}>
     */
    public static array $afterEachCalls = [];

    /**
     * Sets the current describe call.
     */
    public static function startDescribe(DescribeCall $describeCall): void
    {
        self::$describing = $describeCall->description;

        ($describeCall->tests)();
    }

    /**
     * Adds a new before each call.
     */
    public static function beforeEach(BeforeEachCall $beforeEachCall, Closure $setter): void
    {
        $setter($beforeEachCall->describing = self::$describing);
    }

    /**
     * Adds a new test call.
     */
    public static function test(TestCall $testCall, Closure $setter): void
    {
        if (! is_null($testCall->describing = self::$describing)) {
            self::$testCalls[] = [$testCall, $setter];
        } else {
            $setter();
        }
    }

    /**
     * Adds a new before each call.
     */
    public static function afterEach(AfterEachCall $afterEachCall, Closure $setter): void
    {
        if (! is_null(self::$describing)) {
            $afterEachCall->describing = self::$describing;

            self::$afterEachCalls[] = [$afterEachCall, $setter];
        } else {
            $setter();
        }
    }

    public static function endDescribe(DescribeCall $describeCall): void
    {
        $describing = self::$describing;

        self::$describing = null;

        foreach (self::$beforeEachCalls as [$beforeEachCall, $setter]) {
            $setter($describing);
        }

        self::$beforeEachCalls = [];

        foreach (self::$testCalls as [$testCall, $setter]) {
            /** @var TestCall $testCall */
            $testCall->testCaseMethod->description = '`'.$describeCall->description.'` '.$testCall->testCaseMethod->description;

            $setter($describing);
        }

        self::$testCalls = [];

        foreach (self::$afterEachCalls as [$afterEachCall, $setter]) {
            $setter($describing);
        }

        self::$afterEachCalls = [];
    }
}
