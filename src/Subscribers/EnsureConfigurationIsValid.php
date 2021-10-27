<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Exceptions\AttributeNotSupportedYet;
use PHPUnit\Event\TestRunner\Configured;
use PHPUnit\Event\TestRunner\ConfiguredSubscriber;

/**
 * @internal
 */
final class EnsureConfigurationIsValid implements ConfiguredSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Configured $event): void
    {
        $configuration = $event->configuration();

        if ($configuration->processIsolation()) {
            throw new AttributeNotSupportedYet('processIsolation', 'true');
        }
    }
}
