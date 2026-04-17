<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\TestSuite\Finished;
use PHPUnit\Event\TestSuite\Started;

/**
 * @internal
 */
final class EnsureShardTimingsAreCollected
{
    /**
     * The start times for each test class.
     *
     * @var array<string, HRTime>
     */
    private static array $startTimes = [];

    /**
     * The collected timings for each test class.
     *
     * @var array<string, float>
     */
    private static array $timings = [];

    /**
     * Records the start time for a test suite.
     */
    public static function started(Started $event): void
    {
        if (! $event->testSuite()->isForTestClass()) {
            return;
        }

        $name = preg_replace('/^P\\\\/', '', $event->testSuite()->name());

        if (is_string($name)) {
            self::$startTimes[$name] = $event->telemetryInfo()->time();
        }
    }

    /**
     * Records the duration for a test suite.
     */
    public static function finished(Finished $event): void
    {
        if (! $event->testSuite()->isForTestClass()) {
            return;
        }

        $name = preg_replace('/^P\\\\/', '', $event->testSuite()->name());

        if (! is_string($name) || ! isset(self::$startTimes[$name])) {
            return;
        }

        $duration = $event->telemetryInfo()->time()->duration(self::$startTimes[$name]);

        self::$timings[$name] = round($duration->asFloat(), 4);
    }

    /**
     * Returns the collected timings.
     *
     * @return array<string, float>
     */
    public static function timings(): array
    {
        return self::$timings;
    }
}
