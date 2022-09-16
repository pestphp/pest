<?php

declare(strict_types=1);

namespace Pest;

use PHPUnit\TextUI\Application;
use PHPUnit\TextUI\Exception;

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
        Bootstrappers\BootExceptionHandler::class,
        Bootstrappers\BootSubscribers::class,
        Bootstrappers\BootFiles::class,
    ];

    /**
     * Creates a new Kernel instance.
     */
    public function __construct(
        private readonly Application $application
    ) {
        // ..
    }

    /**
     * Boots the Kernel.
     */
    public static function boot(): self
    {
        foreach (self::BOOTSTRAPPERS as $bootstrapper) {
            (new $bootstrapper())->__invoke();
        }

        return new self(new Application());
    }

    /**
     * Handles the given argv.
     *
     * @param  array<int, string>  $argv
     *
     * @throws Exception
     */
    public function handle(array $argv): int
    {
        $argv = (new Plugins\Actions\HandleArguments())->__invoke($argv);

        $this->application->run(
            $argv, false,
        );

        return (new Plugins\Actions\AddsOutput())->__invoke(
            Result::exitCode(),
        );
    }

    /**
     * Shutdown the Kernel.
     */
    public function shutdown(): void
    {
        // ..
    }
}
