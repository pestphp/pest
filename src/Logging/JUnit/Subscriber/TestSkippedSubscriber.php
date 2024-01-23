<?php

declare(strict_types=1);

namespace Pest\Logging\JUnit\Subscriber;

use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;

/**
 * @internal
 */
final class TestSkippedSubscriber extends Subscriber implements SkippedSubscriber
{
    public function notify(Skipped $event): void
    {
        $this->logger()->testSkipped($event);
    }
}
