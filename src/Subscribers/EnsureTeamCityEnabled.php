<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Logging\TeamCity\Converter;
use Pest\Logging\TeamCity\TeamCityLogger;
use Pest\TestSuite;
use PHPUnit\Event\TestRunner\Configured;
use PHPUnit\Event\TestRunner\ConfiguredSubscriber;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class EnsureTeamCityEnabled implements ConfiguredSubscriber
{
    /**
     * Creates a new Configured Subscriber instance.
     */
    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly TestSuite $testSuite,
    ) {
    }

    /**
     * Runs the subscriber.
     */
    public function notify(Configured $event): void
    {
        if (! $this->input->hasParameterOption('--teamcity')) {
            return;
        }

        $flowId = getenv('FLOW_ID');
        $flowId = is_string($flowId) ? (int) $flowId : getmypid();

        new TeamCityLogger(
            $this->output,
            new Converter($this->testSuite->rootPath),
            $flowId === false ? null : $flowId,
            getenv('COLLISION_IGNORE_DURATION') !== false
        );
    }
}
