<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\MarkedIncompleteSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnIncomplete implements MarkedIncompleteSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(MarkedIncomplete $event): void
    {
        $this->collector->testIncomplete($event->throwable()->message());
    }
}
