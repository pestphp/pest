<?php

declare(strict_types=1);

namespace Pest\Logging;

use function getmypid;
use Pest\Concerns\Testable;
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
    }

    public function printResult(TestResult $result): void
    {
        $this->printHeader($result);
        $this->printFooter($result);
    }

    /** @phpstan-ignore-next-line */
    public function startTestSuite(TestSuite $suite): void
    {
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
            $this->writePestTestOutput($test->getName(), 'green', '✓');
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

    protected function writePestTestOutput(string $message, string $color, string $symbol, callable $suffix = null)
    {
        $this->writeProgressWithColor("fg-$color, bold", "$symbol ");
        $this->writeProgress($message);

        if ($suffix) {
            $suffix();
        }

        $this->writeNewLine();
    }

    /**
     * @param Test|Testable $test
     */
    public function addError(Test $test, Throwable $t, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writePestTestOutput($test->getName(), 'red', '⨯');
        $this->phpunitTeamCity->addError($test, $t, $time);
    }

    /**
     * @phpstan-ignore-next-line
     *
     * @param Test|Testable $test
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->phpunitTeamCity->addWarning($test, $e, $time);
    }

    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writePestTestOutput($test->getName(), 'yellow', '!', function() use ($t) {
            $this->writeProgressWithColor('fg-yellow', ' -> ' . $t->getMessage());
        });
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writePestTestOutput($test->getName(), 'red', '⨯');
        $this->phpunitTeamCity->addFailure($test, $e, $time);
    }

    protected function writeProgress(string $progress): void
    {
        parent::writeProgress($progress);
    }

    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
        $this->lastTestFailed = true;
        $this->writePestTestOutput($test->getName(), 'yellow', '-', function() use ($t) {
            $this->writeProgressWithColor('fg-yellow', ' -> ' . $t->getMessage());
        });
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

    protected function countSuccessfulTests(TestResult $result)
    {
        return $result->count()
            - $result->failureCount()
            - $result->errorCount()
            - $result->skippedCount()
            - $result->warningCount();
    }

    protected function printFooter(TestResult $result): void
    {
        $this->writeProgress('Tests:  ');

        $results = [
            'failed' => ['count' => $result->errorCount() + $result->failureCount(), 'color' => 'fg-red'],
            'skipped' => ['count' => $result->skippedCount(), 'color' => 'fg-yellow'],
            'warned' => ['count' => $result->warningCount(), 'color' => 'fg-yellow'],
            'risked' => ['count' => $result->riskyCount(), 'color' => 'fg-yellow'],
            'incomplete' => ['count' => $result->notImplementedCount(), 'color' => 'fg-yellow'],
            'passed' => ['count' => $this->countSuccessfulTests($result), 'color' => 'fg-green'],
        ];

        $filteredResults = array_filter($results, function($item) {
            return $item['count'] > 0;
        });

        foreach ($filteredResults as $key => $result) {
            $this->writeProgressWithColor($result['color'], $result['count'] . " $key");

            if ($key !== array_reverse(array_keys($filteredResults))[0]) {
                $this->write(', ');
            }
        }

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
