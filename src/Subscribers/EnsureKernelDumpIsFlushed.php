<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\KernelDump;
use Pest\Support\Container;
use PHPUnit\Event\TestRunner\Started;
use PHPUnit\Event\TestRunner\StartedSubscriber;

/**
 * @internal
 */
final class EnsureKernelDumpIsFlushed implements StartedSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Started $event): void
    {
        $kernelDump = Container::getInstance()->get(KernelDump::class);

        assert($kernelDump instanceof KernelDump);

        $kernelDump->disable();
    }
}
