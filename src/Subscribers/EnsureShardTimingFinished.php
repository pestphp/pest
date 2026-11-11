<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use PHPUnit\Event\TestSuite\Finished;
use PHPUnit\Event\TestSuite\FinishedSubscriber;

/**
 * @internal
 */
final class EnsureShardTimingFinished implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        EnsureShardTimingsAreCollected::finished($event);
    }
}
