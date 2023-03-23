<?php

declare(strict_types=1);

namespace Pest\Support;

use NunoMaduro\Collision\Adapters\Phpunit\State;
use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use NunoMaduro\Collision\Exceptions\TestOutcome;
use PHPUnit\Event\Code\TestDoxBuilder;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\ThrowableBuilder;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Framework\SkippedWithMessageException;
use PHPUnit\Metadata\MetadataCollection;
use PHPUnit\TestRunner\TestResult\TestResult as PHPUnitTestResult;

final class StateGenerator
{
    public function fromPhpUnitTestResult(int $passedTests, PHPUnitTestResult $testResult): State
    {
        $state = new State();

        foreach ($testResult->testErroredEvents() as $testResultEvent) {
            if ($testResultEvent instanceof Errored) {
                $state->add(TestResult::fromPestParallelTestCase(
                    $testResultEvent->test(),
                    TestResult::FAIL,
                    $testResultEvent->throwable()
                ));
            } else {
                $state->add(TestResult::fromBeforeFirstTestMethodErrored($testResultEvent));
            }
        }

        foreach ($testResult->testFailedEvents() as $testResultEvent) {
            $state->add(TestResult::fromPestParallelTestCase(
                $testResultEvent->test(),
                TestResult::FAIL,
                $testResultEvent->throwable()
            ));
        }

        foreach ($testResult->testMarkedIncompleteEvents() as $testResultEvent) {
            $state->add(TestResult::fromPestParallelTestCase(
                $testResultEvent->test(),
                TestResult::INCOMPLETE,
                $testResultEvent->throwable()
            ));
        }

        foreach ($testResult->testConsideredRiskyEvents() as $riskyEvents) {
            foreach ($riskyEvents as $riskyEvent) {
                $state->add(TestResult::fromPestParallelTestCase(
                    $riskyEvent->test(),
                    TestResult::RISKY,
                    ThrowableBuilder::from(new TestOutcome($riskyEvent->message()))
                ));
            }
        }

        foreach ($testResult->testSkippedEvents() as $testResultEvent) {
            if ($testResultEvent->message() === '__TODO__') {
                $state->add(TestResult::fromPestParallelTestCase($testResultEvent->test(), TestResult::TODO));

                continue;
            }

            $state->add(TestResult::fromPestParallelTestCase(
                $testResultEvent->test(),
                TestResult::SKIPPED,
                ThrowableBuilder::from(new SkippedWithMessageException($testResultEvent->message()))
            ));
        }

        foreach ($testResult->testTriggeredDeprecationEvents() as $testResultEvent) {
            $testResultEvent = $testResultEvent[0];

            $state->add(TestResult::fromPestParallelTestCase(
                $testResultEvent->test(),
                TestResult::DEPRECATED,
                ThrowableBuilder::from(new TestOutcome($testResultEvent->message()))
            ));
        }

        foreach ($testResult->testTriggeredPhpDeprecationEvents() as $testResultEvent) {
            $testResultEvent = $testResultEvent[0];

            $state->add(TestResult::fromPestParallelTestCase(
                $testResultEvent->test(),
                TestResult::DEPRECATED,
                ThrowableBuilder::from(new TestOutcome($testResultEvent->message()))
            ));
        }

        foreach ($testResult->testTriggeredNoticeEvents() as $testResultEvent) {
            $testResultEvent = $testResultEvent[0];

            $state->add(TestResult::fromPestParallelTestCase(
                $testResultEvent->test(),
                TestResult::NOTICE,
                ThrowableBuilder::from(new TestOutcome($testResultEvent->message()))
            ));
        }

        foreach ($testResult->testTriggeredPhpNoticeEvents() as $testResultEvent) {
            $testResultEvent = $testResultEvent[0];

            $state->add(TestResult::fromPestParallelTestCase(
                $testResultEvent->test(),
                TestResult::NOTICE,
                ThrowableBuilder::from(new TestOutcome($testResultEvent->message()))
            ));
        }

        foreach ($testResult->testTriggeredWarningEvents() as $testResultEvent) {
            $testResultEvent = $testResultEvent[0];

            $state->add(TestResult::fromPestParallelTestCase(
                $testResultEvent->test(),
                TestResult::WARN,
                ThrowableBuilder::from(new TestOutcome($testResultEvent->message()))
            ));
        }

        foreach ($testResult->testTriggeredPhpWarningEvents() as $testResultEvent) {
            $testResultEvent = $testResultEvent[0];

            $state->add(TestResult::fromPestParallelTestCase(
                $testResultEvent->test(),
                TestResult::WARN,
                ThrowableBuilder::from(new TestOutcome($testResultEvent->message()))
            ));
        }

        // for each test that passed, we need to add it to the state
        for ($i = 0; $i < $passedTests; $i++) {
            $state->add(TestResult::fromPestParallelTestCase(
                new TestMethod(
                    "$i", // @phpstan-ignore-line
                    '', // @phpstan-ignore-line
                    '',
                    1,
                    TestDoxBuilder::fromClassNameAndMethodName('', ''), // @phpstan-ignore-line
                    MetadataCollection::fromArray([]),
                    TestDataCollection::fromArray([])
                ),
                TestResult::PASS
            ));
        }

        return $state;
    }
}
