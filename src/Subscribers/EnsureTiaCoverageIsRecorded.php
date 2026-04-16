<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Support\Tia\Recorder;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

/**
 * Starts PCOV collection before each test. No-op unless the TIA recorder was
 * activated by the `--tia` plugin.
 *
 * @internal
 */
final class EnsureTiaCoverageIsRecorded implements PreparedSubscriber
{
    public function notify(Prepared $event): void
    {
        $recorder = Recorder::instance();

        if (! $recorder->isActive()) {
            return;
        }

        $test = $event->test();

        if (! $test instanceof TestMethod) {
            return;
        }

        $recorder->beginTest($test->className(), $test->methodName(), $test->file());
    }
}
