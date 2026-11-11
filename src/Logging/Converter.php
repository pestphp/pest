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
use PHPUnit\Event\Test\AfterLastTestMethodErrored;
use PHPUnit\Event\Test\AfterLastTestMethodFailed;
use PHPUnit\Event\Test\BeforeFirstTestMethodErrored;
use PHPUnit\Event\Test\BeforeFirstTestMethodFailed;
use PHPUnit\Event\TestSuite\TestSuite;
use PHPUnit\Event\TestSuite\TestSuiteForTestMethodWithDataProvider;
use PHPUnit\Framework\Exception as FrameworkException;
use PHPUnit\TestRunner\TestResult\TestResult as PhpUnitTestResult;

/**
 * @internal
 */
final readonly class Converter
{
    private const string PREFIX = 'P\\';

    private StateGenerator $stateGenerator;

    public function __construct(
        private string $rootPath,
    ) {
        $this->stateGenerator = new StateGenerator;
    }

    public function getTestCaseMethodName(Test $test): string
    {
        if (! $test instanceof TestMethod) {
            throw ShouldNotHappen::fromMessage('Not an instance of TestMethod');
        }

        return $test->testDox()->prettifiedMethodName();
    }

    public function getTestCaseLocation(Test $test): string
    {
        if (! $test instanceof TestMethod) {
            throw ShouldNotHappen::fromMessage('Not an instance of TestMethod');
        }

        $path = $test->testDox()->prettifiedClassName();
        $relativePath = $this->toRelativePath($path);

        $description = $test->testDox()->prettifiedMethodName();

        return "$relativePath::$description";
    }

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

    public function getStackTrace(Throwable $throwable): string
    {
        $stackTrace = $throwable->stackTrace();

        $frames = explode("\n", $stackTrace);

        $frames = array_filter($frames);

        $frames = array_map(
            $this->toRelativePath(...),
            $frames
        );

        $frames = array_map(
            fn (string $frame): string => "at $frame",
            $frames
        );

        return implode("\n", $frames);
    }

    public function getTestSuiteName(TestSuite $testSuite): string
    {
        if ($testSuite instanceof TestSuiteForTestMethodWithDataProvider) {
            $firstTest = $this->getFirstTest($testSuite);
            if ($firstTest instanceof TestMethod) {
                return $this->getTestMethodNameWithoutDatasetSuffix($firstTest);
            }
        }

        $name = $testSuite->name();

        if (! str_starts_with($name, self::PREFIX)) {
            return $name;
        }

        return Str::after($name, self::PREFIX);
    }

    public function getTrimmedTestClassName(TestMethod $test): string
    {
        return Str::after($test->className(), self::PREFIX);
    }

    public function getTestSuiteLocation(TestSuite $testSuite): ?string
    {
        $firstTest = $this->getFirstTest($testSuite);
        if (! $firstTest instanceof TestMethod) {
            return null;
        }
        $path = $firstTest->testDox()->prettifiedClassName();
        $classRelativePath = $this->toRelativePath($path);

        if ($testSuite instanceof TestSuiteForTestMethodWithDataProvider) {
            $methodName = $this->getTestMethodNameWithoutDatasetSuffix($firstTest);

            return "$classRelativePath::$methodName";
        }

        return $classRelativePath;
    }

    private function getTestMethodNameWithoutDatasetSuffix(TestMethod $testMethod): string
    {
        return Str::beforeLast($testMethod->testDox()->prettifiedMethodName(), ' with data set ');
    }

    private function getFirstTest(TestSuite $testSuite): ?TestMethod
    {
        $tests = $testSuite->tests()->asArray();

        if ($tests === []) {
            return null;
        }

        $firstTest = $tests[0];
        if (! $firstTest instanceof TestMethod) {
            throw ShouldNotHappen::fromMessage('Not an instance of TestMethod');
        }

        return $firstTest;
    }

    public function getTestSuiteSize(TestSuite $testSuite): int
    {
        return $testSuite->count();
    }

    private function toRelativePath(string $path): string
    {
        return str_replace("$this->rootPath".DIRECTORY_SEPARATOR, '', $path);
    }

    public function getStateFromResult(PhpUnitTestResult $result): State
    {
        $events = [
            ...$result->testErroredEvents(),
            ...$result->testFailedEvents(),
            ...$result->testSkippedEvents(),
            ...array_merge(...array_values($result->testConsideredRiskyEvents())),
            ...$result->testMarkedIncompleteEvents(),
        ];

        $notPassedTests = [];

        foreach ($events as $event) {
            if ($event instanceof AfterLastTestMethodErrored) {
                continue;
            }
            if ($event instanceof AfterLastTestMethodFailed) {
                continue;
            }
            if ($event instanceof BeforeFirstTestMethodErrored || $event instanceof BeforeFirstTestMethodFailed) {
                $notPassedTests[] = $event->testClassName();

                continue;
            }

            $notPassedTests[] = $this->getTestCaseLocation($event->test());
        }

        $numberOfPassedTests = $result->numberOfTestsRun()
            - count(array_unique($notPassedTests))
            - $result->numberOfTestSkippedByTestSuiteSkippedEvents();

        return $this->stateGenerator->fromPhpUnitTestResult($numberOfPassedTests, $result);
    }
}
