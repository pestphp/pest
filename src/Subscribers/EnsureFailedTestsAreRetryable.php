<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\TestSuite;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;

/**
 * @internal
 */
final class EnsureFailedTestsAreRetryable implements FailedSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Failed $event): void
    {
        TestSuite::getInstance()->retryRepository->add($event->test()->id());
    }
}
