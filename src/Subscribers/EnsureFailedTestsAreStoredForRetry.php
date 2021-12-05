<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\TestSuite;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;

/**
 * @internal
 */
final class EnsureFailedTestsAreStoredForRetry implements FailedSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Failed $event): void
    {
        TestSuite::getInstance()->retryTempRepository->add($event->test()->id());
    }
}
