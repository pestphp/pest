<?php

declare(strict_types=1);

/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\Runner;

use PHPUnit\Framework\IterativeTestSuite;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Runner\ExecutionOrder\Context;
use PHPUnit\Runner\ExecutionOrder\ReorderPipeline;
use PHPUnit\Runner\TestRunHistory\NullTestRunHistory;
use PHPUnit\Runner\TestRunHistory\TestRunHistory;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class TestSuiteSorter
{
    public const int ORDER_DEFAULT = 0;

    public const int ORDER_RANDOMIZED = 1;

    public const int ORDER_REVERSED = 2;

    public const int ORDER_DEFECTS_FIRST = 3;

    public const int ORDER_DURATION_ASCENDING = 4;

    public const int ORDER_SIZE_ASCENDING = 5;

    public const int ORDER_DURATION_DESCENDING = 6;

    public const int ORDER_SIZE_DESCENDING = 7;

    public const int ORDER_MODIFIED_ASCENDING = 8;

    public const int ORDER_MODIFIED_DESCENDING = 9;

    private readonly TestRunHistory $testRunHistory;

    public function __construct(?TestRunHistory $testRunHistory = null)
    {
        $this->testRunHistory = $testRunHistory ?? new NullTestRunHistory;
    }

    public function apply(Test $suite, ReorderPipeline $pipeline): void
    {
        if ($pipeline->describe() === ['resolve-dependencies'] && ! $this->anyTestHasDependencies([$suite])) {
            return;
        }

        $this->applyRecursively($suite, $pipeline);
    }

    private function applyRecursively(Test $suite, ReorderPipeline $pipeline): void
    {
        if ($suite instanceof IterativeTestSuite) {
            return;
        }

        if (! $suite instanceof TestSuite) {
            return;
        }

        foreach ($suite as $child) {
            $this->applyRecursively($child, $pipeline);
        }

        $tests = $suite->tests();

        if ($tests === []) {
            return;
        }

        $suite->setTests(
            $pipeline->apply(
                $tests,
                new Context($suite, $this->testRunHistory),
            ),
        );
    }

    /**
     * @param  iterable<Test>  $tests
     */
    private function anyTestHasDependencies(iterable $tests): bool
    {
        foreach ($tests as $test) {
            if ($test instanceof TestSuite) {
                if ($this->anyTestHasDependencies($test->tests())) {
                    return true;
                }

                continue;
            }

            if ($test instanceof TestCase && $test->requires() !== []) {
                return true;
            }
        }

        return false;
    }
}
