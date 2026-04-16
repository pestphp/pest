<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PassedSubscriber;

/**
 * @internal
 */
final class EnsureTiaResultIsRecordedOnPassed implements PassedSubscriber
{
    public function __construct(private readonly ResultCollector $collector) {}

    public function notify(Passed $event): void
    {
        $this->collector->testPassed();
    }
}
