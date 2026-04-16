<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\Recorder;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

/**
 * Stops PCOV collection after each test and merges the covered files into the
 * TIA recorder's aggregate map. No-op unless the recorder is active.
 *
 * @internal
 */
final readonly class EnsureTiaCoverageIsFlushed implements FinishedSubscriber
{
    public function __construct(private Recorder $recorder) {}

    public function notify(Finished $event): void
    {
        $this->recorder->endTest();
    }
}
