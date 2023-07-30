<?php

declare(strict_types=1);

namespace Pest\Logging\JUnit\Subscriber;

use Pest\Logging\JUnit\JUnitLogger;

/**
 * @internal
 */
abstract class Subscriber
{
    /**
     * Creates a new Subscriber instance.
     */
    public function __construct(private readonly JUnitLogger $logger)
    {
    }

    /**
     * Creates a new JunitLogger instance.
     */
    final protected function logger(): JUnitLogger
    {
        return $this->logger;
    }
}
