<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;

/**
 * @internal
 */
final class EnsureShardTimingStarted implements StartedSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Started $event): void
    {
        EnsureShardTimingsAreCollected::started($event);
    }
}
