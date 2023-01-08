<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity\Subscriber;

use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

/**
 * @internal
 */
final class TestPreparedSubscriber extends Subscriber implements PreparedSubscriber
{
    public function notify(Prepared $event): void
    {
        $this->logger()->testPrepared($event);
    }
}
