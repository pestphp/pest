<?php

declare(strict_types=1);

namespace Pest\Logging;

use Illuminate\Console\BufferedConsoleOutput;
use NunoMaduro\Collision\Adapters\Phpunit\State;
use NunoMaduro\Collision\Adapters\Phpunit\Style;
use NunoMaduro\Collision\Adapters\Phpunit\TestResult as CollisionTestResult;
use NunoMaduro\Collision\Adapters\Phpunit\Timer;
use Pest\Concerns\Logging\WritesToConsole;
use Pest\Concerns\Testable;

use const PHP_EOL;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\TextUI\DefaultResultPrinter;
use ReflectionClass;
use ReflectionException;

use function round;
use function str_replace;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class TeamCity extends DefaultResultPrinter
{
    use WritesToConsole;

    private const PROTOCOL            = 'pest_qn://';
    private const NAME                = 'name';
    private const LOCATION_HINT       = 'locationHint';
    private const DURATION            = 'duration';
    private const TEST_SUITE_STARTED  = 'testSuiteStarted';
    private const TEST_SUITE_FINISHED = 'testSuiteFinished';
    private const TEST_COUNT          = 'testCount';
    private const TEST_STARTED        = 'testStarted';
    private const TEST_FINISHED       = 'testFinished';

    /** @var int */
    private $flowId;

    /** @var \PHPUnit\Util\Log\TeamCity */
    private $phpunitTeamCity;

    /** @var Style */
    private $style;

    /** @var Style */
    private $bufferedStyle;

    /** @var BufferedConsoleOutput */
    private $bufferedOutput;

    /** @var State */
    private $stateForCurrentTestSuite;

    /** @var State */
    private $stateForAllTestsSuites;

    /**
     * If the test suite has failed.
     *
     * @var bool
     */
    private $failed = false;

    /**
     * We store here all the run tests, so we handle them later together.
     *
     * @var array
     */
    private $storedTestSuites = [];

    /**
     * @var TestSuite
     */
    private $currentTestSuite;

    /**
     * Total time of all run tests.
     *
     * @var float
     */
    private $totalTestsTime = 0;

    /**
     * @param resource|string|null $out
     */
    public function __construct($out, bool $verbose, string $colors)
    {
        parent::__construct($out, $verbose, $colors);

        $this->createNewStyleWriter();

        $this->createNewBufferedStyleWriter();

        $this->phpunitTeamCity = new \PHPUnit\Util\Log\TeamCity($out, $verbose, $colors);

        $this->stateForAllTestsSuites = $this->createNewEmptyState();
    }

    public function startTestSuite(TestSuite $suite): void
    {
        $this->stateForCurrentTestSuite = $this->createNewEmptyState();
        $this->currentTestSuite         = $suite;

        if ($this->stateForCurrentTestSuite->suiteTotalTests === null) {
            $this->stateForCurrentTestSuite->suiteTotalTests = $suite->count();
        }
    }

    /*
     * Leave empty because we deal with tests in printResult.
     * @param TestSuite $suite
     * @return void
     */
    public function endTestSuite(TestSuite $suite): void
    {
        // Store current state of test suite so we are prepared for a new one
        $this->storedTestSuites[$this->currentTestSuite->getName()]['state'] = $this->stateForCurrentTestSuite;

        // Prevent test suite which is only a directory to change name
        if (!is_dir($suite->getName()) && !file_exists($suite->getName())) {
            $this->storedTestSuites[$this->currentTestSuite->getName()]['name'] = $this->getReadableTestSuiteName($suite);
        }
    }

    /**
     * Leave empty because we deal with tests in printResult.
     */
    public function startTest(Test $test): void
    {
    }

    /**
     * Leave almost empty because we deal with tests in printResult.
     * We just check if the test wasn't handled yet, which means it is a passing test.
     */
    public function endTest(Test $test, float $time): void
    {
        // If current test is not yet in current test suite tests, it means it is a passing test.
        // Still, we need to add it now.
        if (!$this->stateForCurrentTestSuite->existsInTestCase($test)) {
            $this->storedTestSuites[$this->currentTestSuite->getName()]['tests'][] = [
                'test'   => $test,
                'status' => '',
                'time'   => $time,
                'state'  => CollisionTestResult::fromTestCase($test, CollisionTestResult::PASS),
            ];

            $this->stateForCurrentTestSuite->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::PASS));
            $this->stateForAllTestsSuites->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::PASS));
        }

        // Add passing tests to state for all tests too.
        if (!$this->stateForAllTestsSuites->existsInTestCase($test)) {
            $this->stateForCurrentTestSuite->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::PASS));
            $this->stateForAllTestsSuites->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::PASS));
        }
    }

    /**
     * Print TeamCity event.
     *
     * @param array<string, string|int> $params
     */
    private function printEvent(string $eventName, array $params = []): void
    {
        $this->style->write("##teamcity[{$eventName}");

        if ($this->flowId !== 0) {
            $params['flowId'] = $this->flowId;
        }

        foreach ($params as $key => $value) {
            $escapedValue = self::escapeValue((string) $value);
            $this->style->write(" {$key}='{$escapedValue}'");
        }

        $this->style->write("]\n");
    }

    /**
     * Leave empty so we can better override the output.
     */
    public function write(string $buffer): void
    {
    }

    /**
     * Here we deal with all the tests and write the output.
     *
     * @throws ReflectionException
     */
    public function printResult(TestResult $result): void
    {
        // Write a list showing the status of all tests (passed, failed, skipped, etc.).
        // For all test suites.
        foreach ($this->storedTestSuites as $testSuiteWithTests) {
            $currentState                = $testSuiteWithTests['state'];
            $currentState->headerPrinted = false;
            $currentState->testCaseName  = $testSuiteWithTests['name'];
            $this->style->writeCurrentTestCaseSummary($currentState);
        }

        if ($this->stateForCurrentTestSuite->suiteTotalTests === null) {
            $this->stateForCurrentTestSuite->suiteTotalTests = $this->currentTestSuite->count();
        }

        // Write test output for each test-suite
        foreach ($this->storedTestSuites as $testSuiteWithTests) {
            $this->printTestSuiteStartedEvent($testSuiteWithTests['name']);

            if ($result->count() === 0) {
                $this->style->writeWarning('No tests executed!');
            }

            // Loop through all tests, start them, write output, and end them
            $totalTime = $this->handleStoredTestsAndWriteOutput($testSuiteWithTests['tests']);

            $this->printTestSuiteFinishedEvent($testSuiteWithTests['name']);
        }

        // Show how many tests passed, failed, etc. and the time
        $this->stateForAllTestsSuites->suiteTotalTests = $this->stateForAllTestsSuites->testSuiteTestsCount();
        $this->style->writeRecap($this->stateForAllTestsSuites, $this->createTimerWithTotalTime($this->totalTestsTime));
    }

    /**
     * Failure listener we use to store the run test and add it to the state.
     */
    public function addFailure(Test $test, AssertionFailedError $error, float $time): void
    {
        $this->failed                                                          = true;
        $this->storedTestSuites[$this->currentTestSuite->getName()]['tests'][] = [
            'test'   => $test,
            'status' => CollisionTestResult::FAIL,
            'error'  => $error,
            'time'   => $time,
            'state'  => CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $error),
        ];
        $this->stateForCurrentTestSuite->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $error));
        $this->stateForAllTestsSuites->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $error));
    }

    /**
     * Skipped listener we use to store the run test and add it to the state.
     */
    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
        $this->storedTestSuites[$this->currentTestSuite->getName()]['tests'][] = [
            'test'      => $test,
            'status'    => CollisionTestResult::SKIPPED,
            'throwable' => $t,
            'time'      => $time,
        ];
        $this->stateForCurrentTestSuite->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::SKIPPED, $t));
        $this->stateForAllTestsSuites->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::SKIPPED, $t));
    }

    /**
     * Error listener we use to store the run test and add it to the state.
     */
    public function addError(Test $test, Throwable $t, float $time): void
    {
        $this->failed                                                          = true;
        $this->storedTestSuites[$this->currentTestSuite->getName()]['tests'][] = [
            'test'      => $test,
            'status'    => CollisionTestResult::FAIL,
            'throwable' => $t,
            'time'      => $time,
            'state'     => CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $t),
        ];

        $this->stateForCurrentTestSuite->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $t));
        $this->stateForAllTestsSuites->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $t));
    }

    /**
     * Warning listener we use to store the run test and add it to the state.
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->storedTestSuites[$this->currentTestSuite->getName()]['tests'][] = [
            'test'    => $test,
            'status'  => CollisionTestResult::WARN,
            'warning' => $e,
            'time'    => $time,
        ];
        $this->stateForCurrentTestSuite->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::WARN, $e));
        $this->stateForAllTestsSuites->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::WARN, $e));
    }

    /**
     * Risky listener we use to store the run test and add it to the state.
     */
    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
        $this->storedTestSuites[$this->currentTestSuite->getName()]['tests'][] = [
            'test'      => $test,
            'status'    => CollisionTestResult::RISKY,
            'throwable' => $t,
            'time'      => $time,
        ];
        $this->stateForCurrentTestSuite->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::RISKY, $t));
        $this->stateForAllTestsSuites->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::RISKY, $t));
    }

    /**
     * Incomplete listener we use to store the run test and add it to the state.
     */
    public function addIncompleteTest(Test $test, Throwable $t, float $time): void
    {
        $this->storedTestSuites[$this->currentTestSuite->getName()]['tests'][] = [
            'test'      => $test,
            'status'    => CollisionTestResult::INCOMPLETE,
            'throwable' => $t,
            'time'      => $time,
        ];

        $this->stateForCurrentTestSuite->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::INCOMPLETE, $t));
        $this->stateForAllTestsSuites->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::INCOMPLETE, $t));
    }

    private static function isPhpUnitTestSuite(TestSuite $suite): bool
    {
        return file_exists($suite->getName()) || !method_exists($suite->getName(), '__getFileName');
    }

    private static function escapeValue(string $text): string
    {
        return str_replace(
            ['|', "'", "\n", "\r", ']', '['],
            ['||', "|'", '|n', '|r', '|]', '|['],
            $text
        );
    }

    private static function toMilliseconds(float $time): int
    {
        return (int) round($time * 1000);
    }

    /**
     * Create timer for a specific time back in time.
     *
     * @throws ReflectionException
     */
    private function createTimerWithTotalTime(float $totalTime): object
    {
        $testInMicroTime = microtime(true) - $totalTime;

        $class = new ReflectionClass(Timer::class);

        $method = $class->getConstructor();
        $method->setAccessible(true);
        $timer = $class->newInstanceWithoutConstructor();
        $method->invoke($timer, $testInMicroTime);

        return $timer;
    }

    protected function createNewStyleWriter(): void
    {
        $output      = new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $this->style = new Style($output);
    }

    protected function createNewBufferedStyleWriter(): void
    {
        $this->bufferedOutput = new class() extends BufferedConsoleOutput {
            protected function doWrite(string $message, bool $newline)
            {
                $this->buffer .= $message;

                if ($newline) {
                    $this->buffer .= PHP_EOL;
                }
            }
        };

        $this->bufferedStyle = new Style($this->bufferedOutput);
    }

    protected function createNewEmptyState(): State
    {
        $dummyTest = new class() extends TestCase {
        };

        return State::from($dummyTest);
    }

    protected function printTestSuiteStartedEvent(string $testSuiteName)
    {
        $suiteName = $this->currentTestSuite->getName();

        // TODO: check location hint
        $this->printEvent(self::TEST_SUITE_STARTED, [
            self::NAME          => $testSuiteName,
            self::LOCATION_HINT => self::PROTOCOL . (TeamCity::isPhpUnitTestSuite($this->currentTestSuite) ? $suiteName : $suiteName::__getFileName()),
        ]);
    }

    protected function printTestSuiteFinishedEvent(string $testSuiteName): void
    {
        $suiteName = $this->currentTestSuite->getName();

        // TODO: check location hint
        $this->printEvent(self::TEST_SUITE_FINISHED, [
            self::NAME          => $testSuiteName,
            self::LOCATION_HINT => self::PROTOCOL . (TeamCity::isPhpUnitTestSuite($this->currentTestSuite) ? $suiteName : $suiteName::__getFileName()),
        ]);
    }

    protected function printTestStartedEvent($test): void
    {
        $this->printEvent(self::TEST_STARTED, [
            self::NAME => $test->getName(),
            // @phpstan-ignore-next-line
            self::LOCATION_HINT => self::PROTOCOL . $test->toString(),
        ]);
    }

    protected function handleStoredTestsAndWriteOutput(array $testSuiteWithTests)
    {
        foreach ($testSuiteWithTests as $testSuitTest) {
            $this->totalTestsTime += $testSuitTest['time'];

            // Let's check first if the testCase is over.
            if ($this->stateForCurrentTestSuite->testCaseHasChanged($testSuitTest['test'])) {
                $this->stateForCurrentTestSuite->moveTo($testSuitTest['test']);
            }

            $this->printTestStartedEvent($testSuitTest['test']);

            // Write output depending on which type of test it is.
            if ($testSuitTest['status'] === CollisionTestResult::FAIL) {
                // Create message and details for TeamCity event.
                $this->bufferedStyle->writeError($testSuitTest['error'] ?? $testSuitTest['throwable']);
                $output              = $this->bufferedOutput->fetch();
                [$message, $details] = explode("\n", $output, 2);

                $currentState = $this->createNewEmptyState();
                $currentState->add($testSuitTest['state']);

                // Write error headline for test
                $this->style->writeErrorsSummary($currentState, false);

                $this->printEventTestFailed($testSuitTest, $message, []);
            } elseif (in_array($testSuitTest['status'], [CollisionTestResult::SKIPPED, CollisionTestResult::INCOMPLETE])) {
                // Write PHPUnit output for skipped and incomplete tests.
                $this->phpunitTeamCity->printIgnoredTest($testSuitTest['test']->getName(), $testSuitTest['throwable'], $testSuitTest['time']);
            } elseif ($testSuitTest['status'] === CollisionTestResult::WARN) {
                // Write PHPUnit output for test with warning.
                $this->phpunitTeamCity->addWarning($testSuitTest['test'], $testSuitTest['warning'], $testSuitTest['time']);
            }

            $this->printEventTestEnded($testSuitTest);
        }
    }

    protected function printEventTestFailed($testData, $message, array $parameters): void
    {
        $this->printEvent('testFailed', array_merge([
            'name'    => $testData['test']->getName(),
            'message' => trim($message),
            // to do add time
            'duration' => self::toMilliseconds($testData['time']),
            'details'  => '',
        ], $parameters));
    }

    protected function printEventTestEnded($testData): void
    {
        $this->printEvent(self::TEST_FINISHED, [
            self::NAME => $testData['test']->getName(),
            // Todo: add time

            self::DURATION => self::toMilliseconds($testData['time'] ?? 0),
        ]);
    }

    public static function isPestTest(Test $test): bool
    {
        /** @var array<string, string> $uses */
        $uses = class_uses($test);

        return in_array(Testable::class, $uses, true);
    }

    public function getReadableTestSuiteName(TestSuite $testSuite): string
    {
        $testSuiteName = $testSuite->getName();

        return $this->isPhpUnitTestSuite($testSuite) ? $testSuiteName : substr($testSuiteName, 2);
    }
}
