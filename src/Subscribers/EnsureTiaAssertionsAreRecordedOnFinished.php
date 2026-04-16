<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

/**
 * Fires last for each test, after the outcome subscribers. Records the exact
 * assertion count so replay can emit the same `addToAssertionCount()` instead
 * of a hardcoded value.
 *
 * @internal
 */
final class EnsureTiaAssertionsAreRecordedOnFinished implements FinishedSubscriber
{
    public function __construct(private readonly ResultCollector $collector) {}

    public function notify(Finished $event): void
    {
        $test = $event->test();

        if ($test instanceof TestMethod) {
            $this->collector->recordAssertions(
                $test->className().'::'.$test->methodName(),
                $event->numberOfAssertionsPerformed(),
            );
        }
    }
}
