<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Contracts\Bootstrapper;
use Pest\Subscribers;
use Pest\Support\Container;
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
        Subscribers\EnsureConfigurationIsAvailable::class,
        Subscribers\EnsureTeamCityEnabled::class,
    ];

    /**
     * Creates a new Subscriber instance.
     */
    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * Boots the Subscribers.
     */
    public function boot(): void
    {
        foreach (self::SUBSCRIBERS as $subscriber) {
            $instance = $this->container->get($subscriber);

            assert($instance instanceof Subscriber);

            Event\Facade::registerSubscriber(
                $instance
            );
        }
    }
}
