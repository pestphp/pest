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
final readonly class BootSubscribers implements Bootstrapper
{
    /**
     * The list of Subscribers.
     *
     * @var array<int, class-string<Subscriber>>
     */
    private const array SUBSCRIBERS = [
        Subscribers\EnsureConfigurationIsAvailable::class,
        Subscribers\EnsureIgnorableTestCasesAreIgnored::class,
        Subscribers\EnsureKernelDumpIsFlushed::class,
        Subscribers\EnsureTeamCityEnabled::class,
        Subscribers\EnsureTiaIsRunningPestTestsOnly::class,
        Subscribers\EnsureTiaStarts::class,
        Subscribers\EnsureTiaEnds::class,
        Subscribers\EnsureTiaResultsAreCollected::class,
        Subscribers\EnsureTiaResultIsRecordedOnPassed::class,
        Subscribers\EnsureTiaResultIsRecordedOnFailed::class,
        Subscribers\EnsureTiaResultIsRecordedOnErrored::class,
        Subscribers\EnsureTiaResultIsRecordedOnSkipped::class,
        Subscribers\EnsureTiaResultIsRecordedOnIncomplete::class,
        Subscribers\EnsureTiaResultIsRecordedOnRisky::class,
        Subscribers\EnsureTiaAssertionsAreRecordedOnFinished::class,
    ];

    /**
     * Creates a new instance of the Boot Subscribers.
     */
    public function __construct(
        private Container $container,
    ) {}

    /**
     * Boots the list of Subscribers.
     */
    public function boot(): void
    {
        foreach (self::SUBSCRIBERS as $subscriber) {
            $instance = $this->container->get($subscriber);

            assert($instance instanceof Subscriber);

            Event\Facade::instance()->registerSubscriber($instance);
        }
    }
}
