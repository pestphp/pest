<?php

declare(strict_types=1);

namespace Pest\Logging;

use function getmypid;
use NunoMaduro\Collision\Adapters\Phpunit\Printer;
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
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Throwable;

final class TeamCity extends DefaultResultPrinter
{
    private const PROTOCOL            = 'pest_qn://';
    private const NAME                = 'name';
    private const LOCATION_HINT       = 'locationHint';
    private const DURATION            = 'duration';
    private const TEST_SUITE_STARTED  = 'testSuiteStarted';
    private const TEST_SUITE_FINISHED = 'testSuiteFinished';

    /**
     * A stack of error messages and test failures to be displayed
     * once the test suite has finished running.
     *
     * @var array<callable>
     */
    protected $outputStack = [];

    /** @var int */
    private $flowId;

    /** @var bool */
    private $isSummaryTestCountPrinted = false;

    /** @var \NunoMaduro\Collision\Adapters\Phpunit\Printer|null Printer */
    private $collisionPrinter = null;

    /**
     * @param resource|string|null $out
     */
    public function __construct($out, bool $verbose, string $colors)
    {
        parent::__construct($out, $verbose, $colors, false, 80, false);
        /* @phpstan-ignore-next-line  */
        if ($out === null || $out instanceof ConsoleOutputInterface) {
            $this->collisionPrinter = new Printer($out, $verbose, $colors);
        }

        $this->writeNewLine();
        $this->write('Pest ' . version());
        $this->writeNewLine();
    }

    public function printResult(TestResult $result): void
    {
        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->printResult($result);
        }
    }

    /** @phpstan-ignore-next-line */
    public function startTestSuite(TestSuite $suite): void
    {
        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->startTestSuite($suite);
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

    /** @phpstan-ignore-next-line */
    public function endTestSuite(TestSuite $suite): void
    {
        $suiteName = $suite->getName();

        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->endTestSuite($suite);
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
            self::NAME => substr($suiteName, 2),
        ]);
    }

    /**
     * @param Test|Testable $test
     */
    public function startTest(Test $test): void
    {
        $this->printEvent('testStarted', [
            self::NAME => $test->getName(),
            // @phpstan-ignore-next-line
            self::LOCATION_HINT => self::PROTOCOL . $test->toString(),
        ]);

        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->startTest($test);
        }
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
        if ($test instanceof TestCase) {
            $this->numAssertions += $test->getNumAssertions();
        } elseif ($test instanceof PhptTestCase) {
            $this->numAssertions++;
        }

        $this->printEvent('testFinished', [
            self::NAME     => $test->getName(),
            self::DURATION => self::toMilliseconds($time),
        ]);

        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->endTest($test, $time);
        }
    }

    private static function toMilliseconds(float $time): int
    {
        return (int) round($time * 1000);
    }

    /**
     * @param Test|Testable $test
     */
    public function addError(Test $test, Throwable $t, float $time): void
    {
        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->addError($test, $t, $time);
        }
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->addFailure($test, $e, $time);
        }
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->addWarning($test, $e, $time);
        }
    }

    public function addIncompleteTest(Test $test, Throwable $t, float $time): void
    {
        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->addIncompleteTest($test, $t, $time);
        }
    }

    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->addRiskyTest($test, $t, $time);
        }
    }

    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
        if ($this->collisionPrinter !== null) {
            $this->collisionPrinter->addSkippedTest($test, $t, $time);
        }
    }
}
