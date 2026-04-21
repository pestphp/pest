<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

/**
 * Starts a per-test recording window on Prepared. Sibling subscribers
 * (`EnsureTia*`) close it with the outcome and the assertion count so the
 * graph can persist everything needed for faithful replay.
 *
 * Why one subscriber per event: PHPUnit's `TypeMap::map()` picks only the
 * first subscriber interface it finds on a class, so one class cannot fan
 * out to multiple events — each event needs its own subscriber class.
 *
 * @internal
 */
final readonly class EnsureTiaResultsAreCollected implements PreparedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(Prepared $event): void
    {
        $test = $event->test();

        if ($test instanceof TestMethod) {
            $this->collector->testPrepared($test->className().'::'.$test->methodName());
        }
    }
}
