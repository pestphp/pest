<?php

declare(strict_types=1);

namespace Pest\Logging;

use NunoMaduro\Collision\Adapters\Phpunit\State;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Support\StateGenerator;
use Pest\Support\Str;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Test\BeforeFirstTestMethodErrored;
use PHPUnit\Event\Test\ConsideredRisky;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\TestSuite\TestSuite;
use PHPUnit\Framework\Exception as FrameworkException;
use PHPUnit\TestRunner\TestResult\TestResult as PhpUnitTestResult;

/**
 * @internal
 */
final class Converter
{
    private const PREFIX = 'P\\';

    private readonly StateGenerator $stateGenerator;

    /**
     * Creates a new instance of the Converter.
     */
    public function __construct(
        private readonly string $rootPath,
    ) {
        $this->stateGenerator = new StateGenerator;
    }

    /**
     * Gets the test case method name.
     */
    public function getTestCaseMethodName(Test $test): string
    {
        if (! $test instanceof TestMethod) {
            throw ShouldNotHappen::fromMessage('Not an instance of TestMethod');
        }

        return $test->testDox()->prettifiedMethodName();
    }

    /**
     * Gets the test case location.
     */
    public function getTestCaseLocation(Test $test): string
    {
        if (! $test instanceof TestMethod) {
            throw ShouldNotHappen::fromMessage('Not an instance of TestMethod');
        }

        $path = $test->testDox()->prettifiedClassName();
        $relativePath = $this->toRelativePath($path);

        // TODO: Get the description without the dataset.
        $description = $test->testDox()->prettifiedMethodName();

        return "$relativePath::$description";
    }

    /**
     * Gets the exception message.
     */
    public function getExceptionMessage(Throwable $throwable): string
    {
        if (is_a($throwable->className(), FrameworkException::class, true)) {
            return $throwable->message();
        }

        $buffer = $throwable->className();
        $throwableMessage = $throwable->message();

        if ($throwableMessage !== '') {
            $buffer .= ": $throwableMessage";
        }

        return $buffer;
    }

    /**
     * Gets the exception details.
     */
    public function getExceptionDetails(Throwable $throwable): string
    {
        $buffer = $this->getStackTrace($throwable);

        while ($throwable->hasPrevious()) {
            $throwable = $throwable->previous();

            $buffer .= sprintf(
                "\nCaused by\n%s\n%s",
                $throwable->description(),
                $this->getStackTrace($throwable)
            );
        }

        return $buffer;
    }

    /**
     * Gets the stack trace.
     */
    public function getStackTrace(Throwable $throwable): string
    {
        $stackTrace = $throwable->stackTrace();

        // Split stacktrace per frame.
        $frames = explode("\n", $stackTrace);

        // Remove empty lines
        $frames = array_filter($frames);

        // clean the paths of each frame.
        $frames = array_map(
            fn (string $frame): string => $this->toRelativePath($frame),
            $frames
        );

        // Format stacktrace as `at <path>`
        $frames = array_map(
            fn (string $frame) => "at $frame",
            $frames
        );

        return implode("\n", $frames);
    }

    /**
     * Gets the test suite name.
     */
    public function getTestSuiteName(TestSuite $testSuite): string
    {
        $name = $testSuite->name();

        if (! str_starts_with($name, self::PREFIX)) {
            return $name;
        }

        return Str::after($name, self::PREFIX);
    }

    /**
     * Gets the trimmed test class name.
     */
    public function getTrimmedTestClassName(TestMethod $test): string
    {
        return Str::after($test->className(), self::PREFIX);
    }

    /**
     * Gets the test suite location.
     */
    public function getTestSuiteLocation(TestSuite $testSuite): ?string
    {
        $tests = $testSuite->tests()->asArray();

        // TODO: figure out how to get the file path without a test being there.
        if ($tests === []) {
            return null;
        }

        $firstTest = $tests[0];
        if (! $firstTest instanceof TestMethod) {
            throw ShouldNotHappen::fromMessage('Not an instance of TestMethod');
        }

        $path = $firstTest->testDox()->prettifiedClassName();

        return $this->toRelativePath($path);
    }

    /**
     * Gets the test suite size.
     */
    public function getTestSuiteSize(TestSuite $testSuite): int
    {
        return $testSuite->count();
    }

    /**
     * Transforms the given path in relative path.
     */
    private function toRelativePath(string $path): string
    {
        // Remove cwd from the path.
        return str_replace("$this->rootPath".DIRECTORY_SEPARATOR, '', $path);
    }

    /**
     * Get the test result.
     */
    public function getStateFromResult(PhpUnitTestResult $result): State
    {
        $events = [
            ...$result->testErroredEvents(),
            ...$result->testFailedEvents(),
            ...$result->testSkippedEvents(),
            ...array_merge(...array_values($result->testConsideredRiskyEvents())),
            ...$result->testMarkedIncompleteEvents(),
        ];

        $numberOfNotPassedTests = count(
            array_unique(
                array_map(
                    function (BeforeFirstTestMethodErrored|Errored|Failed|Skipped|ConsideredRisky|MarkedIncomplete $event): string {
                        if ($event instanceof BeforeFirstTestMethodErrored) {
                            return $event->testClassName();
                        }

                        return $this->getTestCaseLocation($event->test());
                    },
                    $events
                )
            )
        );

        $numberOfPassedTests = $result->numberOfTestsRun() - $numberOfNotPassedTests;

        return $this->stateGenerator->fromPhpUnitTestResult($numberOfPassedTests, $result);
    }
}
