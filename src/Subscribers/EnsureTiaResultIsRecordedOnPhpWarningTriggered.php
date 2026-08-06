<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\PhpWarningTriggered;
use PHPUnit\Event\Test\PhpWarningTriggeredSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnPhpWarningTriggered implements PhpWarningTriggeredSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(PhpWarningTriggered $event): void
    {
        if ($event->wasSuppressed()) {
            return;
        }

        $this->collector->testTriggeredWarning($event->message());
    }
}
