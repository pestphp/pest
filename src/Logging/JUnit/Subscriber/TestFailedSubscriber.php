<?php

declare(strict_types=1);

namespace Pest\Logging\JUnit\Subscriber;

use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;

/**
 * @internal
 */
final class TestFailedSubscriber extends Subscriber implements FailedSubscriber
{
    public function notify(Failed $event): void
    {
        $this->logger()->testFailed($event);
    }
}
