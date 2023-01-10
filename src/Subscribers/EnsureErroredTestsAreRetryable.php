<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\TestSuite;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;

/**
 * @internal
 */
final class EnsureErroredTestsAreRetryable implements ErroredSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Errored $event): void
    {
        TestSuite::getInstance()->retryRepository->add($event->test()->id());
    }
}
