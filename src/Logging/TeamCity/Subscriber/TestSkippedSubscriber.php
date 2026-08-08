<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity\Subscriber;

use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;

/**
 * @internal
 */
final class TestSkippedSubscriber extends Subscriber implements SkippedSubscriber
{
    public function notify(Skipped $event): void
    {
        if ($event->message() === '__TODO__') {
            return;
        }

        $this->logger()->testSkipped($event);
    }
}
