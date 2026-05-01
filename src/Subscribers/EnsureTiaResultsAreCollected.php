<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultsAreCollected implements PreparedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(Prepared $event): void
    {
        $test = $event->test();

        if ($test instanceof TestMethod) {
            $this->collector->testPrepared($test->className().'::'.$test->methodName(), $test->file());
        }
    }
}
