<?php

declare(strict_types=1);

namespace Pest\Support;

use NunoMaduro\Collision\Adapters\Phpunit\State;
use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Framework\SkippedWithMessageException;
use PHPUnit\Metadata\MetadataCollection;
use PHPUnit\TestRunner\TestResult\TestResult as PHPUnitTestResult;

final class StateGenerator
{
    public function fromPhpUnitTestResult(PHPUnitTestResult $testResult): State
    {
        $state = new State();

        foreach ($testResult->testErroredEvents() as $testResultEvent) {
            assert($testResultEvent instanceof Errored);
            $state->add(\NunoMaduro\Collision\Adapters\Phpunit\TestResult::fromTestCase(
                $testResultEvent->test(),
                TestResult::FAIL,
                $testResultEvent->throwable()
            ));
        }

        foreach ($testResult->testFailedEvents() as $testResultEvent) {
            $state->add(TestResult::fromTestCase(
                $testResultEvent->test(),
                TestResult::FAIL,
                $testResultEvent->throwable()
            ));
        }

        foreach ($testResult->testMarkedIncompleteEvents() as $testResultEvent) {
            $state->add(TestResult::fromTestCase(
                $testResultEvent->test(),
                TestResult::INCOMPLETE,
                $testResultEvent->throwable()
            ));
        }

        foreach ($testResult->testConsideredRiskyEvents() as $riskyEvents) {
            foreach ($riskyEvents as $riskyEvent) {
                $state->add(TestResult::fromTestCase(
                    $riskyEvent->test(),
                    TestResult::RISKY,
                    Throwable::from(new IncompleteTestError($riskyEvent->message()))
                ));
            }
        }

        foreach ($testResult->testSkippedEvents() as $testResultEvent) {
            if ($testResultEvent->message() === '__TODO__') {
                $state->add(TestResult::fromTestCase($testResultEvent->test(), TestResult::TODO));

                continue;
            }

            $state->add(TestResult::fromTestCase(
                $testResultEvent->test(),
                TestResult::SKIPPED,
                Throwable::from(new SkippedWithMessageException($testResultEvent->message()))
            ));
        }

        $numberOfPassedTests = $testResult->numberOfTestsRun()
            - $testResult->numberOfTestErroredEvents()
            - $testResult->numberOfTestFailedEvents()
            - $testResult->numberOfTestSkippedEvents()
            - $testResult->numberOfTestsWithTestConsideredRiskyEvents()
            - $testResult->numberOfTestMarkedIncompleteEvents();

        for ($i = 0; $i < $numberOfPassedTests; $i++) {
            $state->add(TestResult::fromTestCase(

                new TestMethod(
                    /** @phpstan-ignore-next-line */
                    "$i",
                    /** @phpstan-ignore-next-line */
                    '',
                    '',
                    1,
                    /** @phpstan-ignore-next-line */
                    TestDox::fromClassNameAndMethodName('', ''),
                    MetadataCollection::fromArray([]),
                    TestDataCollection::fromArray([])
                ),
                TestResult::PASS
            ));
        }

        return $state;
    }
}
