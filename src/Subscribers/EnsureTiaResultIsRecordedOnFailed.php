<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;

/**
 * @internal
 */
final class EnsureTiaResultIsRecordedOnFailed implements FailedSubscriber
{
    public function __construct(private readonly ResultCollector $collector) {}

    public function notify(Failed $event): void
    {
        $this->collector->testFailed($event->throwable()->message());
    }
}
