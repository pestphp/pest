<?php

declare(strict_types=1);

namespace Pest;

use NunoMaduro\Collision\Writer;
use Pest\Contracts\Bootstrapper;
use Pest\Exceptions\FatalException;
use Pest\Exceptions\NoDirtyTestsFound;
use Pest\Plugins\Actions\CallsAddsOutput;
use Pest\Plugins\Actions\CallsBoot;
use Pest\Plugins\Actions\CallsHandleArguments;
use Pest\Plugins\Actions\CallsHandleOriginalArguments;
use Pest\Plugins\Actions\CallsTerminable;
use Pest\Support\Container;
use Pest\Support\Reflection;
use Pest\Support\View;
use PHPUnit\TestRunner\TestResult\Facade;
use PHPUnit\TextUI\Application;
use PHPUnit\TextUI\Configuration\Registry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Whoops\Exception\Inspector;

/**
 * @internal
 */
final readonly class Kernel
{
    /**
     * The Kernel bootstrappers.
     *
     * @var array<int, class-string>
     */
    private const array BOOTSTRAPPERS = [
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
        private Application $application,
        private OutputInterface $output,
    ) {
        //
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
            new Application,
            $output,
        );

        register_shutdown_function($kernel->shutdown(...));

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
     * Terminate the Kernel.
     */
    public function terminate(): void
    {
        $preBufferOutput = Container::getInstance()->get(KernelDump::class);

        assert($preBufferOutput instanceof KernelDump);

        $preBufferOutput->terminate();

        CallsTerminable::execute();
    }

    /**
     * Shutdowns unexpectedly the Kernel.
     */
    public function shutdown(): void
    {
        $this->terminate();

        if (is_array($error = error_get_last())) {
            if (! in_array($error['type'], [E_ERROR, E_CORE_ERROR], true)) {
                return;
            }

            $message = $error['message'];
            $file = $error['file'];
            $line = $error['line'];

            try {
                $writer = new Writer(null, $this->output);

                $throwable = new FatalException($message);

                Reflection::setPropertyValue($throwable, 'line', $line);
                Reflection::setPropertyValue($throwable, 'file', $file);

                $inspector = new Inspector($throwable);

                $writer->write($inspector);
            } catch (Throwable) { // @phpstan-ignore-line
                View::render('components.badge', [
                    'type' => 'ERROR',
                    'content' => sprintf('%s in %s:%d', $message, $file, $line),
                ]);
            }

            exit(1);
        }
    }
}
