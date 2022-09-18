<?php

declare(strict_types=1);

namespace Pest\Logging;

use Carbon\CarbonInterval;
use Illuminate\Console\BufferedConsoleOutput;
use NunoMaduro\Collision\Adapters\Phpunit\Style;
use Pest\Exceptions\ShouldNotHappen;

use const PHP_EOL;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExceptionWrapper;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\TextUI\ResultPrinter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Whoops\Exception\Frame;
use Whoops\Exception\Inspector;

final class TeamCity implements ResultPrinter
{
    /** @var ConsoleOutputInterface */
    private $output;
    /** @var string */
    private $currentWorkingDirectory;
    /** @var Style */
    private $style;

    /** @var BufferedConsoleOutput */
    private $styleOutput;

    public function __construct()
    {
        $this->output                  = new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $this->currentWorkingDirectory = getcwd() ?: ShouldNotHappen::fromMessage("Couldn't get CWD.");
        $this->styleOutput             = new class() extends BufferedConsoleOutput {
            protected function doWrite(string $message, bool $newline): void
            {
                $this->buffer .= $message;

                if ($newline) {
                    $this->buffer .= PHP_EOL;
                }
            }
        };
        $this->style                   = new Style($this->output);
    }

    public function printResult(TestResult $result): void
    {
        // dd($result);
        // TODO: Implement printResult() method.
    }

    public function write(string $buffer): void
    {
        // Do nothing to control all the outputs
    }

    public function addError(Test $test, Throwable $t, float $time): void
    {
        $testCase = $this->testCaseFromTest($test);

        $message = ServiceMessage::testFailed(
            $testCase->getName(),
            $t->getMessage(),
            $this->convertStackTrace($t),
        );
        $this->output($message);
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $testCase = $this->testCaseFromTest($test);

        $message = ServiceMessage::testFailed(
            $testCase->getName(),
            $e->getMessage(),
            $this->convertStackTrace($e)
        );

        $this->output($message);
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $testCase = $this->testCaseFromTest($test);

        if ($e instanceof ExpectationFailedException && ($comparison = $e->getComparisonFailure()) !== null) {
            $message = ServiceMessage::comparisonFailure(
                $testCase->getName(),
                $e->getMessage(),
                '', // TODO: add stacktrace with location on where this happened
                $comparison->getActualAsString(),
                $comparison->getExpectedAsString(),
            );

            $this->output($message);

            return;
        }

        $message = ServiceMessage::testFailed(
            $testCase->getName(),
            $e->getMessage(),
            $this->convertStackTrace($e)
        );

        $this->output($message);
    }

    public function addIncompleteTest(Test $test, Throwable $t, float $time): void
    {
        $testCase = $this->testCaseFromTest($test);

        $text = 'Test is incomplete';
        // Add reason if any exist.
        if ($t->getMessage() !== '') {
            $text .= " with the reason ({$t->getMessage()})";
        }

        $message = ServiceMessage::testIgnored(
            $testCase->getName(),
            $text,
        );
        $this->output($message);
    }

    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
        $testCase = $this->testCaseFromTest($test);
        $out      = $t->getMessage();

        // Remove random stacktrace from no assertion message.
        if (str_starts_with($out, 'This test did not perform any assertions')) {
            $out = 'This test did not perform any assertions.';
        }

        $message = ServiceMessage::testIgnored(
            $testCase->getName(),
            $out,
        );
        $this->output($message);
    }

    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
        $testCase = $this->testCaseFromTest($test);

        $message = ServiceMessage::testIgnored(
            $testCase->getName(),
            'This test was ignored.',
        );
        $this->output($message);
    }

    public function startTestSuite(TestSuite $suite): void
    {
        $message = ServiceMessage::testSuiteStarted(
            $this->getTestSuiteName($suite),
            self::getTestSuiteFilepath($suite),
        );
        $this->output($message);
    }

    public function endTestSuite(TestSuite $suite): void
    {
        $message = ServiceMessage::testSuiteFinished(
            $this->getTestSuiteName($suite),
        );
        $this->output($message);
    }

    public function startTest(Test $test): void
    {
        $testCase = $this->testCaseFromTest($test);

        $message = ServiceMessage::testStarted(
            $testCase->getName(),
            self::getTestCaseLocation($testCase),
        );
        $this->output($message);
    }

    private static function getTestCaseLocation(TestCase $testCase): string
    {
        if (!method_exists($testCase, '__getFileName') && !method_exists($testCase, '__getOriginalDescription')) {
            return $testCase->toString();
        }

        return sprintf(
            '%s::%s',
            /* @phpstan-ignore-next-line */
            $testCase->__getFileName(),
            /* @phpstan-ignore-next-line */
            $testCase->__getOriginalDescription()
        );
    }

    public function endTest(Test $test, float $time): void
    {
        $testCase = $this->testCaseFromTest($test);

        $message = ServiceMessage::testFinished(
            $testCase->getName(),
            CarbonInterval::milliseconds($time * 1000),
        );
        $this->output($message);
    }

    /**
     * Prints out a service message.
     */
    public function output(ServiceMessage $message): void
    {
        $this->output->write("{$message->toString()}\n");
    }

    /**
     * Returns a test case from the given test.
     */
    private function testCaseFromTest(Test $testCase): TestCase
    {
        if (!$testCase instanceof TestCase) {
            throw ShouldNotHappen::fromMessage('Test is not an instance of testcase.');
        }

        return $testCase;
    }

    private static function getTestSuiteFilepath(TestSuite $suite): string
    {
        $name = $suite->getName();

        // Pest test suites have a `__getFileName` method.
        if (file_exists($name) || !method_exists($name, '__getFileName')) {
            return $name;
        }

        return $name::__getFileName();
    }

    private function getTestSuiteName(TestSuite $suite): string
    {
        $name = $suite->getName();

        // Pest test suites have a `__getFileName` method.
        if (file_exists($name) || !method_exists($name, '__getFileName')) {
            // Remove cwd from the path.
            return str_replace("$this->currentWorkingDirectory/", '', $name);
        }

        return substr($name, 2);
    }

    private function convertStackTrace(Throwable $throwable): string
    {
        if ($throwable instanceof ExceptionWrapper) {
            $throwable = $throwable->getOriginalException();
        }
        $inspector = new Inspector($throwable);

        $stacktrace = "{$inspector->getExceptionName()}: {$inspector->getExceptionMessage()}\n";
        $stacktrace .= $this->convertFrames($inspector);

        while ($inspector = $inspector->getPreviousExceptionInspector()) {
            $stacktrace .= "Caused by: {$inspector->getExceptionName()}: {$inspector->getExceptionMessage()}\n";
            $stacktrace .= $this->convertFrames($inspector);
        }

        return $stacktrace;
    }

    private function convertFrames(Inspector $inspector): string
    {
        $result = '';
        /** @var Frame $frame */
        foreach ($inspector->getFrames() as $frame) {
            $file = str_replace("$this->currentWorkingDirectory/", '', $frame->getFile());
            $line = $frame->getLine();
            $result .= "\tat $file:$line\n";
        }

        return $result;
    }
}
