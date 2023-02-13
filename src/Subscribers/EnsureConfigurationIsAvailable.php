<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Support\Container;
use PHPUnit\Event\TestRunner\Configured;
use PHPUnit\Event\TestRunner\ConfiguredSubscriber;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * @internal
 */
final class EnsureConfigurationIsAvailable implements ConfiguredSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Configured $event): void
    {
        Container::getInstance()->add(Configuration::class, $event->configuration());
    }
}
