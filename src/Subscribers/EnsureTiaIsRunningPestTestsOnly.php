<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Exceptions\TiaRequiresPestTests;
use Pest\Panic;
use Pest\Plugins\Tia\Recorder;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaIsRunningPestTestsOnly implements PreparedSubscriber
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

        $className = $test->className();

        if (! class_exists($className, false)) {
            return;
        }

        if (method_exists($className, '__initializeTestCase')) {
            return;
        }

        Panic::with(new TiaRequiresPestTests($className, $test->file()));
    }
}
