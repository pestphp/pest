<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Logging\Converter;
use Pest\Logging\JUnit\JUnitLogger;
use Pest\Support\Container;
use Pest\TestSuite;
use PHPUnit\Event\TestRunner\Configured;
use PHPUnit\Event\TestRunner\ConfiguredSubscriber;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TextUI\Output\DefaultPrinter;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class EnsureJunitEnabled implements ConfiguredSubscriber
{
    /**
     * Creates a new Configured Subscriber instance.
     */
    public function __construct(
        private readonly InputInterface $input,
        private readonly TestSuite $testSuite,
    ) {
    }

    /**
     * Runs the subscriber.
     */
    public function notify(Configured $event): void
    {
        if (! $this->input->hasParameterOption('--log-junit')) {
            return;
        }

        $configuration = Container::getInstance()->get(Configuration::class);
        assert($configuration instanceof Configuration);

        new JUnitLogger(
            DefaultPrinter::from($configuration->logfileJunit()),
            new Converter($this->testSuite->rootPath),
        );
    }
}
