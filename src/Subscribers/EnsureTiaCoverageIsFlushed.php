<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Support\Tia\Recorder;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

/**
 * Stops PCOV collection after each test and merges the covered files into the
 * TIA recorder's aggregate map. No-op unless the recorder is active.
 *
 * @internal
 */
final class EnsureTiaCoverageIsFlushed implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        Recorder::instance()->endTest();
    }
}
