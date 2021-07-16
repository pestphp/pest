<?php

declare(strict_types=1);

namespace Pest\Logging;

use function getmypid;
use Pest\Concerns\Testable;
use function Pest\version;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Runner\PhptTestCase;
use PHPUnit\TextUI\DefaultResultPrinter;
use function round;
use function str_replace;
use Throwable;

final class TeamCity extends DefaultResultPrinter
{
    private const PROTOCOL            = 'pest_qn://';
    private const NAME                = 'name';
    private const LOCATION_HINT       = 'locationHint';
    private const DURATION            = 'duration';
    private const TEST_SUITE_STARTED  = 'testSuiteStarted';
    private const TEST_SUITE_FINISHED = 'testSuiteFinished';

    /** @var int */
    private $flowId;

    /** @var bool */
    private $isSummaryTestCountPrinted = false;

    /** @var \PHPUnit\Util\Log\TeamCity */
    private $phpunitTeamCity;

    /**
     * A stack of error messages and test failures to be displayed
     * once the test suite has finished running.
     *
     * @var array<callable>
     */
    protected $outputStack = [];

    public function __construct(bool $verbose, string $colors)
    {
        parent::__construct(null, $verbose, $colors, false, 80, false);
        $this->phpunitTeamCity = new \PHPUnit\Util\Log\TeamCity(
            null,
            $verbose,
            $colors,
            false,
            80,
            false
        );

        $this->logo();
    }

    /**
     * Outputs Pest's logo and version number at the top of the output.
     */
    private function logo(): void
    {
        $this->writeNewLine();
        $this->write('Pest ' . version());
        $this->writeNewLine();
    }

    public function printResult(TestResult $result): void
    {
        $this->printHeader($result);
        $this->printFooter($result);
    }

    /** @phpstan-ignore-next-line */
    public function startTestSuite(TestSuite $suite): void
    {
        if (str_starts_with($suite->getName(), 'P\\')) {
            $this->writeWithColor('fg-white, bold', ' ' . substr_replace($suite->getName(), '', 0, 2) . ' ');
        }

        $this->flowId = (int) getmypid();

        if (!$this->isSummaryTestCountPrinted) {
            $this->printEvent(
                'testCount',
                ['count' => $suite->count()]
            );
            $this->isSummaryTestCountPrinted = true;
        }

        $suiteName = $suite->getName();

        if (file_exists($suiteName) || !method_exists($suiteName, '__getFileName')) {
            $this->printEvent(
                self::TEST_SUITE_STARTED, [
                self::NAME          => $suiteName,
                self::LOCATION_HINT => self::PROTOCOL . $suiteName,
            ]);

            return;
        }

        $fileName = $suiteName::__getFileName();

        $this->printEvent(
            self::TEST_SUITE_STARTED, [
            self::NAME          => substr($suiteName, 2),
            self::LOCATION_HINT => self::PROTOCOL . $fileName,
        ]);
    }

    /** @phpstan-ignore-next-line */
    public function endTestSuite(TestSuite $suite): void
    {
        $suiteName = $suite->getName();

        if (str_starts_with($suiteName, 'P\\')) {
            $this->writeNewLine();
        }

        if (file_exists($suiteName) || !method_exists($suiteName, '__getFileName')) {
            $this->printEvent(
                self::TEST_SUITE_FINISHED, [
                self::NAME          => $suiteName,
                self::LOCATION_HINT => self::PROTOCOL . $suiteName,
            ]);

            return;
        }

        $this->printEvent(
            self::TEST_SUITE_FINISHED, [
            self::NAME         => substr($suiteName, 2),
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

        $this->printEvent('testStarted', [
            self::NAME          => $test->getName(),
            // @phpstan-ignore-next-line
            self::LOCATION_HINT => self::PROTOCOL . $test->toString(),
        ]);
    }

    /**
     * @param Test|Testable $test
     */
    public function endTest(Test $test, float $time): void
    {
        if (!TeamCity::isPestTest($test)) {
            $this->phpunitTeamCity->endTest($test, $time);

            return;
        }

        if (!$this->lastTestFailed) {
            $this->writePestTestOutput($test->getName(), 'fg-green, bold', '✓');
        }

        if ($test instanceof TestCase) {
            $this->numAssertions += $test->getNumAssertions();
        } elseif ($test instanceof PhptTestCase) {
            $this->numAssertions++;
        }

        $this->lastTestFailed = false;

        $this->printEvent('testFinished', [
            self::NAME     => $test->getName(),
            self::DURATION => self::toMilliseconds($time),
        ]);
    }

    private function writePestTestOutput(string $message, string $color, string $symbol, string $suffix = null): void
    {
        $this->writeProgressWithColor($color, "$symbol ");
        $this->writeProgress($message);

        if ($suffix !== null && strlen($suffix) > 0) {
            $suffix = str_replace("\n", ' ', $suffix);
            $this->writeWithColor($color, " -> $suffix");
        }
    }

    /**
     * @param Test|Testable $test
     */
    public function addError(Test $test, Throwable $t, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writePestTestOutput($test->getName(), 'fg-red, bold', '⨯');

        $this->outputStack[] = function () use ($test, $t, $time): void {
            $this->writeNewLine();
            $this->writeWithColor('fg-red', "• {$test->getPrintableTestCaseName()} > {$test->getName()}");
            $this->phpunitTeamCity->addError($test, $t, $time);
        };
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writePestTestOutput($test->getName(), 'fg-red, bold', '⨯');

        $this->outputStack[] = function () use ($test, $e, $time): void {
            $this->writeNewLine();
            $this->writeWithColor('fg-red', "• {$test->getPrintableTestCaseName()} > {$test->getName()}");
            $this->phpunitTeamCity->addFailure($test, $e, $time);
        };
    }

    /**
     * @phpstan-ignore-next-line
     *
     * @param Test|Testable $test
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writeWarning($test, $e);
    }

    public function addIncompleteTest(Test $test, Throwable $t, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writeWarning($test, $t);
    }

    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writeWarning($test, $t);
    }

    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writeWarning($test, $t);
    }

    /**
     * @param array<string, string|int> $params
     */
    private function printEvent(string $eventName, array $params = []): void
    {
        $this->write("\n##teamcity[{$eventName}");

        if ($this->flowId !== 0) {
            $params['flowId'] = $this->flowId;
        }

        foreach ($params as $key => $value) {
            $escapedValue = self::escapeValue((string) $value);
            $this->write(" {$key}='{$escapedValue}'");
        }

        $this->write("]\n");
    }

    private function writeWarning(Test $test, Throwable $t): void
    {
        $this->writePestTestOutput($test->getName(), 'fg-cyan, bold', '-', $t->getMessage());
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

    protected function printHeader(TestResult $result): void
    {
        foreach ($this->outputStack as $callable) {
            $callable();
        }
    }

    protected function printFooter(TestResult $result): void
    {
        $this->writeNewLine();
        $this->writeProgress('Tests:  ');

        $results = [
            'failed'     => ['count' => $result->errorCount() + $result->failureCount(), 'color' => 'fg-red'],
            'skipped'    => ['count' => $result->skippedCount(), 'color' => 'fg-cyan'],
            'warned'     => ['count' => $result->warningCount(), 'color' => 'fg-cyan'],
            'risked'     => ['count' => $result->riskyCount(), 'color' => 'fg-cyan'],
            'incomplete' => ['count' => $result->notImplementedCount(), 'color' => 'fg-cyan'],
            'passed'     => ['count' => $this->successfulTestCount($result), 'color' => 'fg-green'],
        ];

        $filteredResults = array_filter($results, function ($item): bool {
            return $item['count'] > 0;
        });

        foreach ($filteredResults as $key => $info) {
            $this->writeProgressWithColor($info['color'], $info['count'] . " $key");

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

    public static function isPestTest(Test $test): bool
    {
        /** @var array<string, string> $uses */
        $uses = class_uses($test);

        return in_array(Testable::class, $uses, true);
    }
}
