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
     * @var array<string, HRTime>
     */
    private static array $startTimes = [];

    /**
     * @var array<string, float>
     */
    private static array $timings = [];

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
     * @return array<string, float>
     */
    public static function timings(): array
    {
        return self::$timings;
    }
}
