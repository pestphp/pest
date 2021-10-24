<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use PHPUnit\Event\TestRunner\Configured;
use PHPUnit\Event\TestRunner\ConfiguredSubscriber;

/**
 * @internal
 */
final class EnsureConfigurationDefaults implements ConfiguredSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Configured $event): void
    {
        $configuration = $event->configuration();
    }
}
