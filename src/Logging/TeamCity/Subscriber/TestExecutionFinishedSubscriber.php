<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity\Subscriber;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;

/**
 * @internal
 */
final class TestExecutionFinishedSubscriber extends Subscriber implements ExecutionFinishedSubscriber
{
    public function notify(ExecutionFinished $event): void
    {
        $this->logger()->testExecutionFinished($event);
    }
}
