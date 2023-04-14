<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity;

use NunoMaduro\Collision\Adapters\Phpunit\Style;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Logging\TeamCity\Subscriber\TestConsideredRiskySubscriber;
use Pest\Logging\TeamCity\Subscriber\TestErroredSubscriber;
use Pest\Logging\TeamCity\Subscriber\TestExecutionFinishedSubscriber;
use Pest\Logging\TeamCity\Subscriber\TestFailedSubscriber;
use Pest\Logging\TeamCity\Subscriber\TestFinishedSubscriber;
use Pest\Logging\TeamCity\Subscriber\TestMarkedIncompleteSubscriber;
use Pest\Logging\TeamCity\Subscriber\TestPreparedSubscriber;
use Pest\Logging\TeamCity\Subscriber\TestSkippedSubscriber;
use Pest\Logging\TeamCity\Subscriber\TestSuiteFinishedSubscriber;
use Pest\Logging\TeamCity\Subscriber\TestSuiteStartedSubscriber;
use PHPUnit\Event\EventFacadeIsSealedException;
use PHPUnit\Event\Facade;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\ConsideredRisky;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestSuite\Finished as TestSuiteFinished;
use PHPUnit\Event\TestSuite\Started as TestSuiteStarted;
use PHPUnit\Event\UnknownSubscriberTypeException;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use ReflectionClass;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class TeamCityLogger
{
    private ?HRTime $time = null;

    private bool $isSummaryTestCountPrinted = false;

    /**
     * @throws EventFacadeIsSealedException
     * @throws UnknownSubscriberTypeException
     */
    public function __construct(
        private readonly OutputInterface $output,
        private readonly Converter $converter,
        private readonly int|null $flowId,
        private readonly bool $withoutDuration,
    ) {
        $this->registerSubscribers();
        $this->setFlowId();
    }

    public function testSuiteStarted(TestSuiteStarted $event): void
    {
        $message = ServiceMessage::testSuiteStarted(
            $this->converter->getTestSuiteName($event->testSuite()),
            $this->converter->getTestSuiteLocation($event->testSuite())
        );

        $this->output($message);

        if (! $this->isSummaryTestCountPrinted) {
            $this->isSummaryTestCountPrinted = true;
            $message = ServiceMessage::testSuiteCount(
                $this->converter->getTestSuiteSize($event->testSuite())
            );

            $this->output($message);
        }
    }

    public function testSuiteFinished(TestSuiteFinished $event): void
    {
        $message = ServiceMessage::testSuiteFinished(
            $this->converter->getTestSuiteName($event->testSuite()),
        );

        $this->output($message);
    }

    public function testPrepared(Prepared $event): void
    {
        $message = ServiceMessage::testStarted(
            $this->converter->getTestCaseMethodName($event->test()),
            $this->converter->getTestCaseLocation($event->test()),
        );

        $this->output($message);

        $this->time = $event->telemetryInfo()->time();
    }

    public function testMarkedIncomplete(MarkedIncomplete $event): never
    {
        throw ShouldNotHappen::fromMessage('testMarkedIncomplete not implemented.');
    }

    public function testSkipped(Skipped $event): void
    {
        $message = ServiceMessage::testIgnored(
            $this->converter->getTestCaseMethodName($event->test()),
            'This test was ignored.'
        );

        $this->output($message);
    }

    /**
     * This will trigger in the following scenarios
     * - When an exception is thrown
     */
    public function testErrored(Errored $event): void
    {
        $testName = $this->converter->getTestCaseMethodName($event->test());
        $message = $this->converter->getExceptionMessage($event->throwable());
        $details = $this->converter->getExceptionDetails($event->throwable());

        $message = ServiceMessage::testFailed(
            $testName,
            $message,
            $details,
        );

        $this->output($message);
    }

    /**
     * This will trigger in the following scenarios
     * - When an assertion fails
     */
    public function testFailed(Failed $event): void
    {
        $testName = $this->converter->getTestCaseMethodName($event->test());
        $message = $this->converter->getExceptionMessage($event->throwable());
        $details = $this->converter->getExceptionDetails($event->throwable());

        if ($event->hasComparisonFailure()) {
            $comparison = $event->comparisonFailure();
            $message = ServiceMessage::comparisonFailure(
                $testName,
                $message,
                $details,
                $comparison->actual(),
                $comparison->expected()
            );
        } else {
            $message = ServiceMessage::testFailed(
                $testName,
                $message,
                $details,
            );
        }

        $this->output($message);
    }

    /**
     * This will trigger in the following scenarios
     * - When no assertions in a test
     */
    public function testConsideredRisky(ConsideredRisky $event): void
    {
        $message = ServiceMessage::testIgnored(
            $this->converter->getTestCaseMethodName($event->test()),
            $event->message()
        );

        $this->output($message);
    }

    public function testFinished(Finished $event): void
    {
        if (! $this->time instanceof \PHPUnit\Event\Telemetry\HRTime) {
            throw ShouldNotHappen::fromMessage('Start time has not been set.');
        }

        $testName = $this->converter->getTestCaseMethodName($event->test());
        $duration = $event->telemetryInfo()->time()->duration($this->time)->asFloat();
        if ($this->withoutDuration) {
            $duration = 100;
        }

        $message = ServiceMessage::testFinished(
            $testName,
            (int) ($duration * 1000)
        );

        $this->output($message);
    }

    public function testExecutionFinished(ExecutionFinished $event): void
    {
        $result = TestResultFacade::result();
        $state = $this->converter->getStateFromResult($result);

        assert($this->output instanceof ConsoleOutput);
        $style = new Style($this->output);

        $telemetry = $event->telemetryInfo();

        if ($this->withoutDuration) {
            $reflector = new ReflectionClass($telemetry);

            $property = $reflector->getProperty('current');
            $property->setAccessible(true);
            $snapshot = $property->getValue($telemetry);
            assert($snapshot instanceof Snapshot);

            $telemetry = new Info(
                $snapshot,
                Duration::fromSecondsAndNanoseconds(1, 0),
                $telemetry->memoryUsageSinceStart(),
                $telemetry->durationSincePrevious(),
                $telemetry->memoryUsageSincePrevious(),
            );
        }

        $style->writeRecap($state, $telemetry, $result);
    }

    public function output(ServiceMessage $message): void
    {
        $this->output->writeln("{$message->toString()}");
    }

    /**
     * @throws EventFacadeIsSealedException
     * @throws UnknownSubscriberTypeException
     */
    private function registerSubscribers(): void
    {
        $subscribers = [
            new TestSuiteStartedSubscriber($this),
            new TestSuiteFinishedSubscriber($this),
            new TestPreparedSubscriber($this),
            new TestFinishedSubscriber($this),
            new TestErroredSubscriber($this),
            new TestFailedSubscriber($this),
            new TestMarkedIncompleteSubscriber($this),
            new TestSkippedSubscriber($this),
            new TestConsideredRiskySubscriber($this),
            new TestExecutionFinishedSubscriber($this),
        ];

        Facade::instance()->registerSubscribers(...$subscribers);
    }

    private function setFlowId(): void
    {
        if ($this->flowId === null) {
            return;
        }

        ServiceMessage::setFlowId($this->flowId);
    }
}
