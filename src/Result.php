<?php

declare(strict_types=1);

namespace Pest;

use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * @internal
 */
final class Result
{
    private const SUCCESS_EXIT = 0;

    private const FAILURE_EXIT = 1;

    private const EXCEPTION_EXIT = 2;

    /**
     * If the exit code is different from 0.
     */
    public static function failed(Configuration $configuration, TestResult $result): bool
    {
        return ! self::ok($configuration, $result);
    }

    /**
     * If the exit code is exactly 0.
     */
    public static function ok(Configuration $configuration, TestResult $result): bool
    {
        return self::exitCode($configuration, $result) === self::SUCCESS_EXIT;
    }

    /**
     * Get the test execution's exit code.
     */
    public static function exitCode(Configuration $configuration, TestResult $result): int
    {
        if ($result->wasSuccessfulIgnoringPhpunitWarnings()
            && ! $result->hasTestTriggeredPhpunitWarningEvents()) {
            return self::SUCCESS_EXIT;
        }

        if ($configuration->failOnEmptyTestSuite() && $result->numberOfTests() === 0) {
            return self::FAILURE_EXIT;
        }

        if ($result->wasSuccessfulIgnoringPhpunitWarnings()) {
            if ($configuration->failOnRisky() && $result->hasTestConsideredRiskyEvents()) {
                $returnCode = self::FAILURE_EXIT;
            }

            $warnings = $result->numberOfTestsWithTestTriggeredPhpunitWarningEvents()
                + $result->numberOfTestsWithTestTriggeredWarningEvents()
                + $result->numberOfTestsWithTestTriggeredPhpWarningEvents();

            if ($configuration->failOnWarning() && $warnings > 0) {
                $returnCode = self::FAILURE_EXIT;
            }

            if ($configuration->failOnIncomplete() && $result->hasTestMarkedIncompleteEvents()) {
                $returnCode = self::FAILURE_EXIT;
            }

            if ($configuration->failOnSkipped() && $result->hasTestSkippedEvents()) {
                $returnCode = self::FAILURE_EXIT;
            }
        }

        if ($result->hasTestErroredEvents()) {
            return self::EXCEPTION_EXIT;
        }

        return self::FAILURE_EXIT;
    }
}
