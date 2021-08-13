<?php

declare(strict_types=1);

namespace Pest\Logging;

use function getmypid;
use Pest\Concerns\Logging\WritesToConsole;
use Pest\Concerns\Testable;
use Pest\Support\ExceptionTrace;
use function Pest\version;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\TextUI\DefaultResultPrinter;
use function round;
use function str_replace;
use function strlen;
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

    /** @var bool */
    private $isSummaryTestCountPrinted = false;

    /** @var \PHPUnit\Util\Log\TeamCity */
    private $phpunitTeamCity;

    /**
     * @param resource|string|null $out
     */
    public function __construct($out, bool $verbose, string $colors)
    {
        parent::__construct($out, $verbose, $colors);
        $this->phpunitTeamCity = new \PHPUnit\Util\Log\TeamCity($out, $verbose, $colors);

        $this->logo();
    }

    private function logo(): void
    {
        $this->writeNewLine();
        $this->write('Pest ' . version());
        $this->writeNewLine();
    }

    public function printResult(TestResult $result): void
    {
        $this->write('Tests:  ');

        $results = [
            'failed'     => ['count' => $result->errorCount() + $result->failureCount(), 'color' => 'fg-red'],
            'skipped'    => ['count' => $result->skippedCount(), 'color' => 'fg-yellow'],
            'warned'     => ['count' => $result->warningCount(), 'color' => 'fg-yellow'],
            'risked'     => ['count' => $result->riskyCount(), 'color' => 'fg-yellow'],
            'incomplete' => ['count' => $result->notImplementedCount(), 'color' => 'fg-yellow'],
            'passed'     => ['count' => $this->successfulTestCount($result), 'color' => 'fg-green'],
        ];

        $filteredResults = array_filter($results, function ($item): bool {
            return $item['count'] > 0;
        });

        foreach ($filteredResults as $key => $info) {
            $this->writeWithColor($info['color'], $info['count'] . " $key", false);

            if ($key !== array_reverse(array_keys($filteredResults))[0]) {
                $this->write(', ');
            }
        }

        $this->writeNewLine();
        $this->write("Assertions:  $this->numAssertions");

        $this->writeNewLine();
        $this->write("Time:  {$result->time()}s");

        $this->writeNewLine();
    }

    private function successfulTestCount(TestResult $result): int
    {
        return $result->count()
            - $result->failureCount()
            - $result->errorCount()
            - $result->skippedCount()
            - $result->warningCount()
            - $result->notImplementedCount()
            - $result->riskyCount();
    }

    /** @phpstan-ignore-next-line */
    public function startTestSuite(TestSuite $suite): void
    {
        $suiteName = $suite->getName();

        if (static::isCompoundTestSuite($suite)) {
            $this->writeWithColor('bold', '  ' . $suiteName);
        } elseif (static::isPestTestSuite($suite)) {
            $this->writeWithColor('fg-white, bold', '  ' . substr_replace($suiteName, '', 0, 2) . ' ');
        } else {
            $this->writeWithColor('fg-white, bold', '  ' . $suiteName);
        }

        $this->writeNewLine();

        $this->flowId = (int) getmypid();

        if (!$this->isSummaryTestCountPrinted) {
            $this->printEvent(self::TEST_COUNT, [
                'count' => $suite->count(),
            ]);
            $this->isSummaryTestCountPrinted = true;
        }

        $this->printEvent(self::TEST_SUITE_STARTED, [
            self::NAME          => static::isCompoundTestSuite($suite) ? $suiteName : substr($suiteName, 2),
            self::LOCATION_HINT => self::PROTOCOL . (static::isCompoundTestSuite($suite) ? $suiteName : $suiteName::__getFileName()),
        ]);
    }

    /**
     * @param array<string, string|int> $params
     */
    private function printEvent(string $eventName, array $params = []): void
    {
        $this->write("##teamcity[{$eventName}");

        if ($this->flowId !== 0) {
            $params['flowId'] = $this->flowId;
        }

        foreach ($params as $key => $value) {
            $escapedValue = self::escapeValue((string) $value);
            $this->write(" {$key}='{$escapedValue}'");
        }

        $this->write("]\n");
    }

    private static function escapeValue(string $text): string
    {
        return str_replace(
            ['|', "'", "\n", "\r", ']', '['],
            ['||', "|'", '|n', '|r', '|]', '|['],
            $text
        );
    }

    /** @phpstan-ignore-next-line */
    public function endTestSuite(TestSuite $suite): void
    {
        $suiteName = $suite->getName();

        $this->writeNewLine();
        $this->writeNewLine();

        $this->printEvent(self::TEST_SUITE_FINISHED, [
            self::NAME          => static::isCompoundTestSuite($suite) ? $suiteName : substr($suiteName, 2),
            self::LOCATION_HINT => self::PROTOCOL . (static::isCompoundTestSuite($suite) ? $suiteName : $suiteName::__getFileName()),
        ]);
    }

    /**
     * @param Test|Testable $test
     */
    public function startTest(Test $test): void
    {
        if (!TeamCity::isPestTest($test)) {
            $this->phpunitTeamCity->startTest($test);

            return;
        }

        $this->printEvent(self::TEST_STARTED, [
            self::NAME => $test->getName(),
            // @phpstan-ignore-next-line
            self::LOCATION_HINT => self::PROTOCOL . $test->toString(),
        ]);
    }

    /**
     * Verify that the given test suite is a valid Pest suite.
     *
     * @param TestSuite<Test> $suite
     */
    private static function isPestTestSuite(TestSuite $suite): bool
    {
        return strncmp($suite->getName(), 'P\\', strlen('P\\')) === 0;
    }

    /**
     * Determine if the test suite is made up of multiple smaller test suites.
     *
     * @param TestSuite<Test> $suite
     */
    private static function isCompoundTestSuite(TestSuite $suite): bool
    {
        return file_exists($suite->getName()) || !method_exists($suite->getName(), '__getFileName');
    }

    public static function isPestTest(Test $test): bool
    {
        /** @var array<string, string> $uses */
        $uses = class_uses($test);

        return in_array(Testable::class, $uses, true);
    }

    /**
     * @param Test|Testable $test
     */
    public function endTest(Test $test, float $time): void
    {
        $this->printEvent(self::TEST_FINISHED, [
            self::NAME     => $test->getName(),
            self::DURATION => self::toMilliseconds($time),
        ]);

        if (!$this->lastTestFailed) {
            $this->writeSuccess($test->getName());
        }

        $this->numAssertions += $test instanceof TestCase ? $test->getNumAssertions() : 1;
        $this->lastTestFailed = false;
    }

    private static function toMilliseconds(float $time): int
    {
        return (int) round($time * 1000);
    }

    public function addError(Test $test, Throwable $t, float $time): void
    {
        $this->markAsFailure($t);
        $this->writeError($test->getName());
        $this->phpunitTeamCity->addError($test, $t, $time);
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->markAsFailure($e);
        $this->writeError($test->getName());
        $this->phpunitTeamCity->addFailure($test, $e, $time);
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->markAsFailure($e);
        $this->writeWarning($test->getName());
        $this->phpunitTeamCity->addWarning($test, $e, $time);
    }

    public function addIncompleteTest(Test $test, Throwable $t, float $time): void
    {
        $this->markAsFailure($t);
        $this->writeWarning($test->getName());
        $this->phpunitTeamCity->addIncompleteTest($test, $t, $time);
    }

    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
        $this->markAsFailure($t);
        $this->writeWarning($test->getName());
        $this->phpunitTeamCity->addRiskyTest($test, $t, $time);
    }

    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
        $this->markAsFailure($t);
        $this->writeWarning($test->getName());
        $this->phpunitTeamCity->printIgnoredTest($test->getName(), $t, $time);
    }

    private function markAsFailure(Throwable $t): void
    {
        $this->lastTestFailed = true;
        ExceptionTrace::removePestReferences($t);
    }
}
