<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\Recorder;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaEnds implements FinishedSubscriber
{
    public function __construct(private Recorder $recorder) {}

    public function notify(Finished $event): void
    {
        $this->recorder->endTest();
    }
}
