<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity\Subscriber;

use PHPUnit\Event\TestSuite\Finished;
use PHPUnit\Event\TestSuite\FinishedSubscriber;

/**
 * @internal
 */
final class TestSuiteFinishedSubscriber extends Subscriber implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        $this->logger()->testSuiteFinished($event);
    }
}
