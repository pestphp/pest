<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\Recorder;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

/**
 * Starts PCOV collection before each test. Pest tests start from
 * `Testable::setUp()` so Laravel boot is covered; this subscriber remains the
 * fallback for PHPUnit-style tests and is idempotent for Pest tests.
 *
 * @internal
 */
final readonly class EnsureTiaCoverageIsRecorded implements PreparedSubscriber
{
    public function __construct(private Recorder $recorder) {}

    public function notify(Prepared $event): void
    {
        if (! $this->recorder->isActive()) {
            return;
        }

        $test = $event->test();

        if (! $test instanceof TestMethod) {
            return;
        }

        $this->recorder->beginTest($test->className(), $test->methodName(), $test->file());
    }
}
