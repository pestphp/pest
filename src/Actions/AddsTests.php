<?php

declare(strict_types=1);

namespace Pest\Actions;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\WarningTestCase;

/**
 * @internal
 */
final class AddsTests
{
    /**
     * Adds tests to the given test suite.
     *
     * @param TestSuite<\PHPUnit\Framework\TestCase> $testSuite
     */
    public static function to(TestSuite $testSuite, \Pest\TestSuite $pestTestSuite): void
    {
        self::removeTestClosureWarnings($testSuite);

        // @todo refactor this...

        $testSuites = [];
        $pestTestSuite->tests->build($pestTestSuite, function (TestCase $testCase) use (&$testSuites): void {
            $testCaseClass = get_class($testCase);
            if (!array_key_exists($testCaseClass, $testSuites)) {
                $testSuites[$testCaseClass] = [];
            }

            $testSuites[$testCaseClass][] = $testCase;
        });

        foreach ($testSuites as $testCaseName => $testCases) {
            $testTestSuite = new TestSuite($testCaseName);
            $testTestSuite->setTests([]);
            foreach ($testCases as $testCase) {
                $testTestSuite->addTest($testCase, $testCase->getGroups());
            }
            $testSuite->addTestSuite($testTestSuite);
        }
    }

    /**
     * @param TestSuite<\PHPUnit\Framework\TestCase> $testSuite
     */
    private static function removeTestClosureWarnings(TestSuite $testSuite): void
    {
        $tests = $testSuite->tests();

        foreach ($tests as $key => $test) {
            if ($test instanceof TestSuite) {
                self::removeTestClosureWarnings($test);
            }

            if ($test instanceof WarningTestCase) {
                unset($tests[$key]);
            }
        }

        $testSuite->setTests($tests);
    }
}
