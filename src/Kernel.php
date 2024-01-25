<?php

declare(strict_types=1);

namespace Pest;

use Pest\Contracts\Bootstrapper;
use Pest\Exceptions\NoDirtyTestsFound;
use Pest\Plugins\Actions\CallsAddsOutput;
use Pest\Plugins\Actions\CallsBoot;
use Pest\Plugins\Actions\CallsHandleArguments;
use Pest\Plugins\Actions\CallsHandleOriginalArguments;
use Pest\Plugins\Actions\CallsShutdown;
use Pest\Support\Container;
use PHPUnit\TestRunner\TestResult\Facade;
use PHPUnit\TextUI\Application;
use PHPUnit\TextUI\Configuration\Registry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Kernel
{
    /**
     * The Kernel bootstrappers.
     *
     * @var array<int, class-string>
     */
    private const BOOTSTRAPPERS = [
        Bootstrappers\BootOverrides::class,
        Bootstrappers\BootSubscribers::class,
        Bootstrappers\BootFiles::class,
        Bootstrappers\BootView::class,
        Bootstrappers\BootKernelDump::class,
        Bootstrappers\BootExcludeList::class,
    ];

    /**
     * Creates a new Kernel instance.
     */
    public function __construct(
        private readonly Application $application,
        private readonly OutputInterface $output,
    ) {
        register_shutdown_function(fn () => $this->shutdown());
    }

    /**
     * Boots the Kernel.
     */
    public static function boot(TestSuite $testSuite, InputInterface $input, OutputInterface $output): self
    {
        $container = Container::getInstance();

        $container
            ->add(TestSuite::class, $testSuite)
            ->add(InputInterface::class, $input)
            ->add(OutputInterface::class, $output)
            ->add(Container::class, $container);

        $kernel = new self(
            new Application(),
            $output,
        );

        foreach (self::BOOTSTRAPPERS as $bootstrapper) {
            $bootstrapper = Container::getInstance()->get($bootstrapper);
            assert($bootstrapper instanceof Bootstrapper);

            $bootstrapper->boot();
        }

        CallsBoot::execute();

        Container::getInstance()->add(self::class, $kernel);

        return $kernel;
    }

    /**
     * Runs the application, and returns the exit code.
     *
     * @param  array<int, string>  $originalArguments
     * @param  array<int, string>  $arguments
     */
    public function handle(array $originalArguments, array $arguments): int
    {
        CallsHandleOriginalArguments::execute($originalArguments);

        $arguments = CallsHandleArguments::execute($arguments);

        try {
            $this->application->run($arguments);
        } catch (NoDirtyTestsFound) {
            $this->output->writeln([
                '',
                '  <fg=white;options=bold;bg=blue> INFO </> No tests found.',
                '',
            ]);
        }

        $configuration = Registry::get();
        $result = Facade::result();

        return CallsAddsOutput::execute(
            Result::exitCode($configuration, $result),
        );
    }

    /**
     * Shutdown the Kernel.
     */
    public function shutdown(): void
    {
        $preBufferOutput = Container::getInstance()->get(KernelDump::class);

        assert($preBufferOutput instanceof KernelDump);

        $preBufferOutput->shutdown();

        CallsShutdown::execute();
    }
}
