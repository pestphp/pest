<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnSkipped implements SkippedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(Skipped $event): void
    {
        $this->collector->testSkipped($event->message());
    }
}
