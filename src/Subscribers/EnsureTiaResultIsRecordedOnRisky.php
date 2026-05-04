<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\ConsideredRisky;
use PHPUnit\Event\Test\ConsideredRiskySubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnRisky implements ConsideredRiskySubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(ConsideredRisky $event): void
    {
        $this->collector->testRisky($event->message());
    }
}
