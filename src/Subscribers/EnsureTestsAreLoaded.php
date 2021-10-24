<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use PHPUnit\Event\TestSuite\Loaded;
use PHPUnit\Event\TestSuite\LoadedSubscriber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\WarningTestCase;

/**
 * @internal
 */
final class EnsureTestsAreLoaded implements LoadedSubscriber
{
    /**
     * The current test suite, if any.
     */
    private static ?TestSuite $testSuite = null;

    /**
     * Runs the subscriber.
     */
    public function notify(Loaded $event): void
    {
        $this->removeWarnings(self::$testSuite);

        $testSuites = [];

        $testSuite = \Pest\TestSuite::getInstance();
        $testSuite->tests->build($testSuite, function (TestCase $testCase) use (&$testSuites): void {
            $testCaseClass = $testCase::class;
            if (!array_key_exists($testCaseClass, $testSuites)) {
                $testSuites[$testCaseClass] = [];
            }

            $testSuites[$testCaseClass][] = $testCase;
        });

        foreach ($testSuites as $testCaseName => $testCases) {
            $testTestSuite = new TestSuite($testCaseName);
            $testTestSuite->setTests([]);
            foreach ($testCases as $testCase) {
                $testTestSuite->addTest($testCase, $testCase->groups());
            }
            self::$testSuite->addTestSuite($testTestSuite);
        }
    }

    /**
     * Sets the current test suite.
     */
    public static function setTestSuite(TestSuite $testSuite): void
    {
        self::$testSuite = $testSuite;
    }

    /**
     * Removes the test case that have "empty" warnings.
     */
    private function removeWarnings(TestSuite $testSuite): void
    {
        $tests = $testSuite->tests();

        foreach ($tests as $key => $test) {
            if ($test instanceof TestSuite) {
                $this->removeWarnings($test);
            }

            if ($test instanceof WarningTestCase) {
                unset($tests[$key]);
            }
        }

        $testSuite->setTests(array_values($tests));
    }
}
