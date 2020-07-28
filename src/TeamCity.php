<?php

namespace Pest;

use function getmypid;
use Pest\Concerns\TestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\TextUI\DefaultResultPrinter;
use function round;
use function str_replace;

class TeamCity extends DefaultResultPrinter
{
    private const PROTOCOL = 'pest_qn://';

    private $flowId;
    private $isSummaryTestCountPrinted = false;
    private $phpunitTeamCity;

    public function __construct($out = null, bool $verbose = false, string $colors = self::COLOR_DEFAULT, bool $debug = false, $numberOfColumns = 80, bool $reverse = false)
    {
        parent::__construct($out, $verbose, $colors, $debug, $numberOfColumns, $reverse);
        $this->phpunitTeamCity = new \PHPUnit\Util\Log\TeamCity(
            $out,
            $verbose,
            $colors,
            $debug,
            $numberOfColumns,
            $reverse
        );
    }

    public function printResult(TestResult $result): void
    {
        $this->printHeader($result);
        $this->printFooter($result);
    }

    public function startTestSuite(TestSuite $suite): void
    {
        $this->flowId = getmypid();

        if (!$this->isSummaryTestCountPrinted) {
            $this->printEvent(
                'testCount',
                ['count' => $suite->count()]
            );
            $this->isSummaryTestCountPrinted = true;
        }

        $suiteName = $suite->getName();

        if (file_exists($suiteName)) {
            $this->printEvent('testSuiteStarted', [
                'name'         => $suiteName,
                'locationHint' => self::PROTOCOL . $suiteName,
            ]);

            return;
        }

        $fileName = $suite->getName()::__getFileName();

        $this->printEvent('testSuiteStarted', [
            'name'         => substr($suiteName, 2),
            'locationHint' => self::PROTOCOL . $fileName,
        ]);
    }

    public function endTestSuite(TestSuite $suite): void
    {
        $suiteName = $suite->getName();

        if (file_exists($suiteName)) {
            $this->printEvent('testSuiteFinished', [
                'name'         => $suiteName,
                'locationHint' => self::PROTOCOL . $suiteName,
            ]);

            return;
        }

        $this->printEvent('testSuiteFinished', [
            'name'         => substr($suiteName, 2),
        ]);
    }

    /**
     * @param Test|TestCase $test
     */
    public function startTest(Test $test): void
    {
        if (!TeamCity::isPestTest($test)) {
            $this->phpunitTeamCity->startTest($test);

            return;
        }

        $this->printEvent('testStarted', [
            'name'         => $test->getName(),
            'locationHint' => self::PROTOCOL . $test->toString(),
        ]);
    }

    /**
     * @param Test|TestCase $test
     */
    public function endTest(Test $test, float $time): void
    {
        if (!TeamCity::isPestTest($test)) {
            $this->phpunitTeamCity->endTest($test, $time);

            return;
        }

        $this->printEvent('testFinished', [
            'name'     => $test->getName(),
            'duration' => self::toMilliseconds($time),
        ]);
    }

    /**
     * @param Test|TestCase $test
     */
    public function addError(Test $test, \Throwable $t, float $time): void
    {
        $this->printEvent('testFailed', [
            'name'     => $test->getName(),
            'message'  => $t->getMessage(),
            'details'  => $t->getTraceAsString(),
            'duration' => self::toMilliseconds($time),
        ]);
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->printEvent('testFailed', [
            'name'     => $test->getName(),
            'message'  => $e->getMessage(),
            'details'  => $e->getTraceAsString(),
            'duration' => self::toMilliseconds($time),
        ]);
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->phpunitTeamCity->addFailure($test, $e, $time);
    }

    protected function writeProgress(string $progress): void
    {
    }

    private function printEvent(string $eventName, array $params = []): void
    {
        $this->write("\n##teamcity[{$eventName}");

        if ($this->flowId) {
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

    private static function toMilliseconds(float $time): int
    {
        return (int) round($time * 1000);
    }

    private static function isPestTest($test): bool
    {
        return in_array(TestCase::class, class_uses($test));
    }
}
