<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\NoticeTriggered;
use PHPUnit\Event\Test\NoticeTriggeredSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnNoticeTriggered implements NoticeTriggeredSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(NoticeTriggered $event): void
    {
        if ($event->wasSuppressed()) {
            return;
        }

        $this->collector->testTriggeredNotice($event->message());
    }
}
