<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity\Subscriber;

use PHPUnit\Event\Test\ConsideredRisky;
use PHPUnit\Event\Test\ConsideredRiskySubscriber;

/**
 * @internal
 */
final class TestConsideredRiskySubscriber extends Subscriber implements ConsideredRiskySubscriber
{
    public function notify(ConsideredRisky $event): void
    {
        $this->logger()->testConsideredRisky($event);
    }
}
