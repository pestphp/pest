<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\DeprecationTriggeredSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnDeprecationTriggered implements DeprecationTriggeredSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(DeprecationTriggered $event): void
    {
        if ($event->wasSuppressed()) {
            return;
        }

        $this->collector->testTriggeredDeprecation($event->message());
    }
}
