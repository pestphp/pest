<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\TestSuite;
use PHPUnit\Event\TestRunner\Started;
use PHPUnit\Event\TestRunner\StartedSubscriber;

/**
 * @internal
 */
final class EnsureRetryRepositoryExists implements StartedSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Started $event): void
    {
        TestSuite::getInstance()->retryRepository->boot();
    }
}
