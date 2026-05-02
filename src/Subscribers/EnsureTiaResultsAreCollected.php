<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultsAreCollected implements PreparationStartedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(PreparationStarted $event): void
    {
        $test = $event->test();

        if ($test instanceof TestMethod) {
            $this->collector->testPrepared($test->className().'::'.$test->methodName(), $test->file());
        }
    }
}
