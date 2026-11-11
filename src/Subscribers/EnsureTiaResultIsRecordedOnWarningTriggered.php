<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\WarningTriggered;
use PHPUnit\Event\Test\WarningTriggeredSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnWarningTriggered implements WarningTriggeredSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(WarningTriggered $event): void
    {
        if ($event->wasSuppressed()) {
            return;
        }

        $this->collector->testTriggeredWarning($event->message());
    }
}
