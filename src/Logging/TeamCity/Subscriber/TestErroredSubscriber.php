<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity\Subscriber;

use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;

/**
 * @internal
 */
final class TestErroredSubscriber extends Subscriber implements ErroredSubscriber
{
    public function notify(Errored $event): void
    {
        $this->logger()->testErrored($event);
    }
}
