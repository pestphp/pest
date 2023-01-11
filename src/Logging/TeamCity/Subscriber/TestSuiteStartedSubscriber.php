<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity\Subscriber;

use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;

/**
 * @internal
 */
final class TestSuiteStartedSubscriber extends Subscriber implements StartedSubscriber
{
    public function notify(Started $event): void
    {
        $this->logger()->testSuiteStarted($event);
    }
}
