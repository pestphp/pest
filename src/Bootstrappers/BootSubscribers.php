<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Contracts\Bootstrapper;
use Pest\Subscribers;
use PHPUnit\Event;
use PHPUnit\Event\Subscriber;

/**
 * @internal
 */
final class BootSubscribers implements Bootstrapper
{
    /**
     * The Kernel subscribers.
     *
     * @var array<int, class-string<Subscriber>>
     */
    private const SUBSCRIBERS = [
        Subscribers\EnsureConfigurationIsValid::class,
        Subscribers\EnsureConfigurationDefaults::class,
        Subscribers\EnsureRetryRepositoryExists::class,
        Subscribers\EnsureErroredTestsAreRetryable::class,
        Subscribers\EnsureFailedTestsAreRetryable::class,
    ];

    /**
     * Boots the Subscribers.
     */
    public function boot(): void
    {
        foreach (self::SUBSCRIBERS as $subscriber) {
            Event\Facade::registerSubscriber(
                new $subscriber()
            );
        }
    }
}
