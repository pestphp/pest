<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\PhpNoticeTriggered;
use PHPUnit\Event\Test\PhpNoticeTriggeredSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnPhpNoticeTriggered implements PhpNoticeTriggeredSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(PhpNoticeTriggered $event): void
    {
        if ($event->wasSuppressed()) {
            return;
        }

        $this->collector->testTriggeredNotice($event->message());
    }
}
