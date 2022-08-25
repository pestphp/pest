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
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\TextUI\DefaultResultPrinter;
use ReflectionClass;
use ReflectionException;

use SebastianBergmann\Comparator\ComparisonFailure;
use function round;
use function str_replace;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use const PHP_EOL;

final class TeamCity extends DefaultResultPrinter
{
    use WritesToConsole;

    private const PROTOCOL = 'pest_qn://';
    private const NAME = 'name';
    private const LOCATION_HINT = 'locationHint';
    private const DURATION = 'duration';
    private const TEST_SUITE_STARTED = 'testSuiteStarted';
    private const TEST_SUITE_FINISHED = 'testSuiteFinished';
    private const TEST_COUNT = 'testCount';
    private const TEST_STARTED = 'testStarted';
    private const TEST_FINISHED = 'testFinished';

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
    private $state;

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
    private $storedTests = [];

    /**
     * @var TestSuite
     */
    private $testSuite;

    /**
     * @param resource|string|null $out
     */
    public function __construct($out, bool $verbose, string $colors)
    {
        parent::__construct($out, $verbose, $colors);

        $this->createNewStyleWriter();

        $this->createNewBufferedStyleWriter();

        $this->phpunitTeamCity = new \PHPUnit\Util\Log\TeamCity($out, $verbose, $colors);

        $this->state = $this->createNewEmptyState();
    }

    public function startTestSuite(TestSuite $suite): void
    {
        $this->testSuite = $suite;
        $this->state->testCaseName = $this->testSuite->getName();

        if ($this->state->suiteTotalTests === null) {
            $this->state->suiteTotalTests = $suite->count();
        }
    }

    /*
     * Leave empty because we deal with tests in printResult.
     * @param TestSuite $suite
     * @return void
     */
    public function endTestSuite(TestSuite $suite): void
    {
    }

    /**
     * Leave empty because we deal with tests in printResult.
     * @param Test $test
     * @return void
     */
    public function startTest(Test $test): void
    {
    }

    /**
     * Leave almost empty because we deal with tests in printResult.
     * We just check if the test wasn't handled yet, which means it is a passing test.
     * @param Test $test
     * @param float $time
     * @return void
     */
    public function endTest(Test $test, float $time): void
    {
        if (! $this->state->existsInTestCase($test)) {
            $this->storedTests[] = ['test' => $test, 'status' => '', 'time' => $time];
            $this->state->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::PASS));
        }
    }

    /**
     * Print TeamCity event
     * @param array<string, string|int> $params
     */
    private function printEvent(string $eventName, array $params = []): void
    {
        $this->style->write("##teamcity[{$eventName}");

        if ($this->flowId !== 0) {
            $params['flowId'] = $this->flowId;
        }

        foreach ($params as $key => $value) {
            $escapedValue = self::escapeValue((string)$value);
            $this->style->write(" {$key}='{$escapedValue}'");
        }

        $this->style->write("]\n");
    }

    /**
     * Leave empty so we can better override the output.
     * @param string $buffer
     * @return void
     */
    public function write(string $buffer): void
    {
    }

    /**
     * Here we deal with all the tests and write the output.
     *
     * @param TestResult $result
     * @return void
     * @throws ReflectionException
     */
    public function printResult(TestResult $result): void
    {
        // Write a list showing the status of all tests (passed, failed, skipped, etc.).
        $this->style->writeCurrentTestCaseSummary($this->state);

        if ($this->state->suiteTotalTests === null) {
            $this->state->suiteTotalTests = $this->testSuite->count();
        }

        $this->printTestSuiteStartedEvent();

        if ($result->count() === 0) {
            $this->style->writeWarning('No tests executed!');
        }

        // Loop through all tests, start them, write output, and end them
        $totalTime = $this->handleStoredTestsAndWriteOutput();

        // Show how many tests passed, failed, etc. and the time
        $this->style->writeRecap($this->state, $this->createTimerWithTotalTime($totalTime));

        $this->printTestSuiteFinishedEvent();
    }

    /**
     * Failure listener we use to store the run test and add it to the state.
     *
     * @param Test $test
     * @param AssertionFailedError $error
     * @param float $time
     * @return void
     */
    public function addFailure(Test $test, AssertionFailedError $error, float $time): void
    {
        $this->failed = true;
        $this->storedTests[] = [
            'test' => $test,
            'status' => CollisionTestResult::FAIL,
            'error' => $error,
            'time' => $time,
            'state' => CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $error)
        ];
        $this->state->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $error));
    }

    /**
     * Skipped listener we use to store the run test and add it to the state.
     *
     * @param Test $test
     * @param Throwable $t
     * @param float $time
     * @return void
     */
    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
        $this->storedTests[] = [
            'test' => $test,
            'status' => CollisionTestResult::SKIPPED,
            'throwable' => $t,
            'time' => $time,
        ];
        $this->state->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::SKIPPED, $t));
    }

    /**
     * Error listener we use to store the run test and add it to the state.
     *
     * @param Test $test
     * @param Throwable $t
     * @param float $time
     * @return void
     */
    public function addError(Test $test, Throwable $t, float $time): void
    {
        $this->failed = true;
        $this->storedTests[] = [
            'test' => $test,
            'status' => CollisionTestResult::FAIL,
            'throwable' => $t,
            'time' => $time,
            'state' => CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $t)
        ];

        $this->state->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::FAIL, $t));

    }

    /**
     * Warning listener we use to store the run test and add it to the state.
     *
     * @param Test $test
     * @param Warning $e
     * @param float $time
     * @return void
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->storedTests[] = [
            'test' => $test,
            'status' => CollisionTestResult::WARN,
            'warning' => $e,
            'time' => $time,
        ];
        $this->state->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::WARN, $e));

    }

    /**
     * Risky listener we use to store the run test and add it to the state.
     *
     * @param Test $test
     * @param Throwable $t
     * @param float $time
     * @return void
     */
    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
        $this->storedTests[] = [
            'test' => $test,
            'status' => CollisionTestResult::RISKY,
            'throwable' => $t,
            'time' => $time,
        ];
        $this->state->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::RISKY, $t));
    }

    /**
     * Incomplete listener we use to store the run test and add it to the state.
     *
     * @param Test $test
     * @param Throwable $t
     * @param float $time
     * @return void
     */
    public function addIncompleteTest(Test $test, Throwable $t, float $time): void
    {
        $this->storedTests[] = [
            'test' => $test,
            'status' => CollisionTestResult::INCOMPLETE,
            'throwable' => $t,
            'time' => $time,
        ];

        $this->state->add(CollisionTestResult::fromTestCase($test, CollisionTestResult::INCOMPLETE, $t));
    }

    /**
     * Determine if the test suite is made up of multiple smaller test suites.
     *
     * @param TestSuite<Test> $suite
     * @return bool
     */
    private static function isCompoundTestSuite(TestSuite $suite): bool
    {
        return file_exists($suite->getName()) || ! method_exists($suite->getName(), '__getFileName');
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
        return (int)round($time * 1000);
    }

    /**
     * Create timer for a specific time back in time.
     *
     * @param float $totalTime
     * @return object
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
        $output = new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $this->style = new Style($output);
    }

    protected function createNewBufferedStyleWriter(): void
    {
        $this->bufferedOutput = new class extends BufferedConsoleOutput {
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

    protected function printTestSuiteStartedEvent()
    {
        $suiteName = $this->testSuite->getName();
        $this->printEvent(self::TEST_SUITE_STARTED, [
            self::NAME => TeamCity::isCompoundTestSuite($this->testSuite) ? $suiteName : substr($suiteName, 2),
            self::LOCATION_HINT => self::PROTOCOL . (TeamCity::isCompoundTestSuite($this->testSuite) ? $suiteName : $suiteName::__getFileName()),
        ]);
    }

    protected function printTestStartedEvent($test1): void
    {
        $this->printEvent(self::TEST_STARTED, [
            self::NAME => $test1->getName(),
            // @phpstan-ignore-next-line
            self::LOCATION_HINT => self::PROTOCOL . $test1->toString(),
        ]);
    }

    protected function handleStoredTestsAndWriteOutput()
    {
        $totalTime = 0;

        foreach ($this->storedTests as $testData) {
            $totalTime += $testData['time'];

            // Let's check first if the testCase is over.
            if ($this->state->testCaseHasChanged($testData['test'])) {
                $this->state->moveTo($testData['test']);
            }

            $this->printTestStartedEvent($testData['test']);

            // Write output depending on which type of test it is.
            if ($testData['status'] === CollisionTestResult::FAIL) {

                // Create message and details for TeamCity event.
                $this->bufferedStyle->writeError($testData['error'] ?? $testData['throwable']);
                $output = $this->bufferedOutput->fetch();
                [$message, $details] = explode("\n", $output, 2);

                $currentState = $this->createNewEmptyState();
                $currentState->add($testData['state']);

                // Write error headline for test
                $this->style->writeErrorsSummary($currentState, false);

                // Add comparison info
                // Taken from vendor/phpunit/phpunit/src/Util/Log/TeamCity.php:104:126
                // Added to handle comparisonFailure type events.
                $parameters = [];
                if (isset($testData['error']) && $testData['error'] instanceof ExpectationFailedException) {
                    $comparisonFailure = $testData['error']->getComparisonFailure();

                    if ($comparisonFailure instanceof ComparisonFailure) {
                        $expectedString = $comparisonFailure->getExpectedAsString();

                        if ($expectedString === null || empty($expectedString)) {
                            $expectedString = self::getPrimitiveValueAsString($comparisonFailure->getExpected());
                        }

                        $actualString = $comparisonFailure->getActualAsString();

                        if ($actualString === null || empty($actualString)) {
                            $actualString = self::getPrimitiveValueAsString($comparisonFailure->getActual());
                        }

                        if ($actualString !== null && $expectedString !== null) {
                            $parameters['type'] = 'comparisonFailure';
                            $parameters['actual'] = $actualString;
                            $parameters['expected'] = $expectedString;
                        }
                    }
                }

                $this->printEventTestFailed($testData, $message, $parameters);
            } elseif (in_array($testData['status'], [CollisionTestResult::SKIPPED, CollisionTestResult::INCOMPLETE])) {
                // Write PHPUnit output for skipped and incomplete tests.
                $this->phpunitTeamCity->printIgnoredTest($testData['test']->getName(), $testData['throwable'], $testData['time']);
            } elseif ($testData['status'] === CollisionTestResult::WARN) {
                // Write PHPUnit output for test with warning.
                $this->phpunitTeamCity->addWarning($testData['test'], $testData['warning'], $testData['time']);
            }

            $this->printEventTestEnded($testData);
        }

        return $totalTime;
    }

    protected function printTestSuiteFinishedEvent(): void
    {
        $suiteName = $this->testSuite->getName();

        $this->printEvent(self::TEST_SUITE_FINISHED, [
            self::NAME => TeamCity::isCompoundTestSuite($this->testSuite) ? $suiteName : substr($suiteName, 2),
            self::LOCATION_HINT => self::PROTOCOL . (TeamCity::isCompoundTestSuite($this->testSuite) ? $suiteName : $suiteName::__getFileName()),
        ]);
    }

    protected function printEventTestFailed($testData, $message, array $parameters): void
    {
        $this->printEvent('testFailed', array_merge([
            'name' => $testData['test']->getName(),
            'message' => trim($message),
            // to do add time
            'duration' => self::toMilliseconds($testData['time']),
            'details' => '',
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
}
