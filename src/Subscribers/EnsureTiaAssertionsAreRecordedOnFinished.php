<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Plugins\Tia\ResultCollector;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

/**
 * @internal
 */
final readonly class EnsureTiaAssertionsAreRecordedOnFinished implements FinishedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    public function notify(Finished $event): void
    {
        $test = $event->test();

        if ($test instanceof TestMethod) {
            $this->collector->recordAssertions(
                $test->className().'::'.$test->methodName(),
                $event->numberOfAssertionsPerformed(),
            );
        }

        $this->collector->finishTest();
    }
}
