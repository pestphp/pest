<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggeredSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnPhpDeprecationTriggered implements PhpDeprecationTriggeredSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(PhpDeprecationTriggered $event): void
    {
        if ($event->wasSuppressed()) {
            return;
        }

        $this->collector->testTriggeredDeprecation($event->message());
    }
}
