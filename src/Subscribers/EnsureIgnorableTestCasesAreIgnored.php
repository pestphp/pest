<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use PHPUnit\Event\TestRunner\Started;
use PHPUnit\Event\TestRunner\StartedSubscriber;
use PHPUnit\Event\TestRunner\WarningTriggered;
use PHPUnit\TestRunner\TestResult\Collector;
use PHPUnit\TestRunner\TestResult\Facade;
use ReflectionClass;

/**
 * @internal
 */
final class EnsureIgnorableTestCasesAreIgnored implements StartedSubscriber
{
    public function notify(Started $event): void
    {
        $reflection = new ReflectionClass(Facade::class);
        $property = $reflection->getProperty('collector');
        $collector = $property->getValue();

        assert($collector instanceof Collector);

        $reflection = new ReflectionClass($collector);
        $property = $reflection->getProperty('testRunnerTriggeredWarningEvents');

        /** @var array<int, WarningTriggered> $testRunnerTriggeredWarningEvents */
        $testRunnerTriggeredWarningEvents = $property->getValue($collector);

        $testRunnerTriggeredWarningEvents = array_values(array_filter($testRunnerTriggeredWarningEvents, fn (WarningTriggered $event): bool => str_contains($event->message(), 'No tests found in class') === false));

        $property->setValue($collector, $testRunnerTriggeredWarningEvents);
    }
}
