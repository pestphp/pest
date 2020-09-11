<?php

declare(strict_types=1);

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
use Throwable;

final class TeamCity extends DefaultResultPrinter
{
    private const PROTOCOL            = 'pest_qn://';
    private const NAME                = 'name';
    private const LOCATION_HINT       = 'locationHint';
    private const DURATION            = 'duration';
    private const TEST_SUITE_STARTED  = 'testSuiteStarted';
    private const TEST_SUITE_FINISHED = 'testSuiteFinished';
    private const TEST_FAILED         = 'testFailed';

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
        $this->flowId = getmypid();

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
     * @param Test|TestCase $test
     */
    public function startTest(Test $test): void
    {
        if (!TeamCity::isPestTest($test)) {
            $this->phpunitTeamCity->startTest($test);

            return;
        }

        $this->printEvent('testStarted', [
            self::NAME          => $test->getName(),
            /* @phpstan-ignore-next-line */
            self::LOCATION_HINT => self::PROTOCOL . $test->toString(),
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
            self::NAME     => $test->getName(),
            self::DURATION => self::toMilliseconds($time),
        ]);
    }

    /**
     * @param Test|TestCase $test
     */
    public function addError(Test $test, Throwable $t, float $time): void
    {
        if (!TeamCity::isPestTest($test)) {
            $this->phpunitTeamCity->addError($test, $t, $time);

            return;
        }

        $this->printEvent(
            self::TEST_FAILED, [
            self::NAME     => $test->getName(),
            'message'      => $t->getMessage(),
            'details'      => $t->getTraceAsString(),
            self::DURATION => self::toMilliseconds($time),
        ]);
    }

    /**
     * @phpstan-ignore-next-line
     *
     * @param Test|TestCase $test
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        if (!TeamCity::isPestTest($test)) {
            $this->phpunitTeamCity->addWarning($test, $e, $time);

            return;
        }

        $this->printEvent(
            self::TEST_FAILED, [
            self::NAME     => $test->getName(),
            'message'      => $e->getMessage(),
            'details'      => $e->getTraceAsString(),
            self::DURATION => self::toMilliseconds($time),
        ]);
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->phpunitTeamCity->addFailure($test, $e, $time);
    }

    protected function writeProgress(string $progress): void
    {
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

    private static function isPestTest(Test $test): bool
    {
        return in_array(TestCase::class, class_uses($test), true);
    }
}
