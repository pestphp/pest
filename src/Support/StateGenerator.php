<?php

declare(strict_types=1);

namespace Pest\Support;

use NunoMaduro\Collision\Adapters\Phpunit\State;
use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use NunoMaduro\Collision\Exceptions\TestOutcome;
use PHPUnit\Event\Code\TestDoxBuilder;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\ThrowableBuilder;
use PHPUnit\Event\Test\ConsideredRisky;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\PhpunitDeprecationTriggered;
use PHPUnit\Event\Test\PhpunitErrorTriggered;
use PHPUnit\Event\Test\PhpunitNoticeTriggered;
use PHPUnit\Event\Test\PhpunitWarningTriggered;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Event\TestRunner\DeprecationTriggered;
use PHPUnit\Event\TestRunner\NoticeTriggered;
use PHPUnit\Event\TestRunner\WarningTriggered;
use PHPUnit\Framework\SkippedWithMessageException;
use PHPUnit\Metadata\MetadataCollection;
use PHPUnit\TestRunner\TestResult\Issues\Issue;
use PHPUnit\TestRunner\TestResult\TestResult as PHPUnitTestResult;

final class StateGenerator
{
    public function fromPhpUnitTestResult(int $passedTests, PHPUnitTestResult $testResult): State
    {
        $state = new State;

        foreach ($testResult->testErroredEvents() as $testResultEvent) {
            if ($testResultEvent instanceof Errored) {
                $state->add(TestResult::fromPestParallelTestCase(
                    $testResultEvent->test(),
                    TestResult::FAIL,
                    $testResultEvent->throwable()
                ));
            } else {
                // @phpstan-ignore-next-line
                $state->add(TestResult::fromBeforeFirstTestMethodErrored($testResultEvent));
            }
        }

        foreach ($testResult->testFailedEvents() as $testResultEvent) {
            if ($testResultEvent instanceof Failed) {
                $state->add(TestResult::fromPestParallelTestCase(
                    $testResultEvent->test(),
                    TestResult::FAIL,
                    $testResultEvent->throwable()
                ));
            } else {
                // @phpstan-ignore-next-line
                $state->add(TestResult::fromBeforeFirstTestMethodErrored($testResultEvent));
            }
        }

        $this->addTriggeredPhpunitEvents($state, $testResult->testTriggeredPhpunitErrorEvents(), TestResult::FAIL);

        $this->addThrowableEvents($state, $testResult->testMarkedIncompleteEvents(), TestResult::INCOMPLETE);
        $this->addTriggeredPhpunitEvents($state, $testResult->testConsideredRiskyEvents(), TestResult::RISKY);

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

        $this->addIssueEvents($state, $testResult->deprecations(), TestResult::DEPRECATED);
        $this->addIssueEvents($state, $testResult->phpDeprecations(), TestResult::DEPRECATED);
        $this->addTriggeredPhpunitEvents($state, $testResult->testTriggeredPhpunitDeprecationEvents(), TestResult::DEPRECATED);

        $this->addIssueEvents($state, $testResult->notices(), TestResult::NOTICE);
        $this->addIssueEvents($state, $testResult->phpNotices(), TestResult::NOTICE);
        $this->addTriggeredPhpunitEvents($state, $testResult->testTriggeredPhpunitNoticeEvents(), TestResult::NOTICE);

        $this->addIssueEvents($state, $testResult->warnings(), TestResult::WARN);
        $this->addIssueEvents($state, $testResult->phpWarnings(), TestResult::WARN);
        $this->addTriggeredPhpunitEvents($state, $testResult->testTriggeredPhpunitWarningEvents(), TestResult::WARN);

        $this->addIssueEvents($state, $testResult->errors(), TestResult::FAIL);

        foreach ($testResult->testSuiteSkippedEvents() as $index => $testResultEvent) {
            $this->addStandaloneEvent(
                $state,
                $testResultEvent->testSuite()->name(),
                TestResult::SKIPPED,
                $testResultEvent->message(),
                $index,
            );
        }

        $this->addStandaloneEvents($state, $testResult->testRunnerTriggeredDeprecationEvents(), 'PHPUnit test runner deprecation', TestResult::DEPRECATED);
        $this->addStandaloneEvents($state, $testResult->testRunnerTriggeredNoticeEvents(), 'PHPUnit test runner notice', TestResult::NOTICE);
        $this->addStandaloneEvents($state, $testResult->testRunnerTriggeredWarningEvents(), 'PHPUnit test runner warning', TestResult::WARN);

        // for each test that passed, we need to add it to the state
        for ($i = 0; $i < $passedTests; $i++) {
            $state->add(TestResult::fromPestParallelTestCase(
                new TestMethod(
                    "$i", // @phpstan-ignore-line
                    '', // @phpstan-ignore-line
                    '', // @phpstan-ignore-line
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

    /**
     * @param  list<Issue>  $issues
     */
    private function addIssueEvents(State $state, array $issues, string $type): void
    {
        foreach ($issues as $issue) {
            foreach ($issue->triggeringTests() as ['test' => $test]) {
                $state->add(TestResult::fromPestParallelTestCase(
                    $test,
                    $type,
                    ThrowableBuilder::from(new TestOutcome($issue->description()))
                ));
            }
        }
    }

    /**
     * @param  list<Failed|MarkedIncomplete>  $events
     */
    private function addThrowableEvents(State $state, array $events, string $type): void
    {
        foreach ($events as $event) {
            $state->add(TestResult::fromPestParallelTestCase(
                $event->test(),
                $type,
                $event->throwable(),
            ));
        }
    }

    /**
     * @param  array<string, list<ConsideredRisky|PhpunitDeprecationTriggered|PhpunitErrorTriggered|PhpunitNoticeTriggered|PhpunitWarningTriggered>>  $testResultEvents
     */
    private function addTriggeredPhpunitEvents(State $state, array $testResultEvents, string $type): void
    {
        foreach ($testResultEvents as $events) {
            foreach ($events as $event) {
                if (! $event->test()->isTestMethod()) {
                    continue;
                }

                $state->add(TestResult::fromPestParallelTestCase(
                    $event->test(),
                    $type,
                    ThrowableBuilder::from(new TestOutcome($event->message()))
                ));
            }
        }
    }

    /**
     * @param  list<DeprecationTriggered|NoticeTriggered|WarningTriggered>  $events
     */
    private function addStandaloneEvents(State $state, array $events, string $className, string $type): void
    {
        foreach ($events as $index => $event) {
            $this->addStandaloneEvent($state, $className, $type, $event->message(), $index);
        }
    }

    private function addStandaloneEvent(State $state, string $className, string $type, string $message, int $index): void
    {
        $methodName = 'event#'.$index;

        $state->add(TestResult::fromPestParallelTestCase(
            new TestMethod(
                $className, // @phpstan-ignore-line
                $methodName, // @phpstan-ignore-line
                ' ', // @phpstan-ignore-line
                1,
                TestDoxBuilder::fromClassNameAndMethodName($className, $methodName), // @phpstan-ignore-line
                MetadataCollection::fromArray([]),
                TestDataCollection::fromArray([]),
            ),
            $type,
            ThrowableBuilder::from(new TestOutcome($message))
        ));
    }
}
