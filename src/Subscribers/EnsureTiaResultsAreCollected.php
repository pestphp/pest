<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\ConsideredRisky;
use PHPUnit\Event\Test\ConsideredRiskySubscriber;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\MarkedIncompleteSubscriber;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PassedSubscriber;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;

/**
 * Feeds per-test outcomes (status + message + time) into the TIA
 * `ResultCollector` so the graph can persist them for faithful replay.
 *
 * @internal
 */
final class EnsureTiaResultsAreCollected implements
    ConsideredRiskySubscriber,
    ErroredSubscriber,
    FailedSubscriber,
    MarkedIncompleteSubscriber,
    PassedSubscriber,
    PreparedSubscriber,
    SkippedSubscriber
{
    public function __construct(private readonly ResultCollector $collector) {}

    public function notify(Prepared|Passed|Failed|Errored|Skipped|MarkedIncomplete|ConsideredRisky $event): void
    {
        if ($event instanceof Prepared) {
            $test = $event->test();

            if ($test instanceof TestMethod) {
                $this->collector->testPrepared($test->className().'::'.$test->methodName());
            }

            return;
        }

        if ($event instanceof Passed) {
            $this->collector->testPassed();

            return;
        }

        if ($event instanceof Failed) {
            $this->collector->testFailed($event->throwable()->message());

            return;
        }

        if ($event instanceof Errored) {
            $this->collector->testErrored($event->throwable()->message());

            return;
        }

        if ($event instanceof Skipped) {
            $this->collector->testSkipped($event->message());

            return;
        }

        if ($event instanceof MarkedIncomplete) {
            $this->collector->testIncomplete($event->throwable()->message());

            return;
        }

        // Last possible type: ConsideredRisky (all others returned above).
        $this->collector->testRisky($event->message()); // @phpstan-ignore method.notFound
    }
}
