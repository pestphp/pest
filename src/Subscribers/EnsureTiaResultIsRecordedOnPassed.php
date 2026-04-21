<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PassedSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaResultIsRecordedOnPassed implements PassedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(Passed $event): void
    {
        $this->collector->testPassed();
    }
}
