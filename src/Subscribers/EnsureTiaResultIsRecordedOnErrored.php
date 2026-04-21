<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnErrored implements ErroredSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(Errored $event): void
    {
        $this->collector->testErrored($event->throwable()->message());
    }
}
