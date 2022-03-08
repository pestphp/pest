<?php

declare(strict_types=1);

namespace Pest;

use PHPUnit\TextUI\Application;

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
    private static array $bootstrappers = [
        Bootstrappers\BootExceptionHandler::class,
        Bootstrappers\BootSubscribers::class,
        Bootstrappers\BootFiles::class,
    ];

    /**
     * Creates a new Kernel instance.
     */
    public function __construct(
        private Application $application
    ) {
        // ..
    }

    /**
     * Boots the Kernel.
     */
    public static function boot(): self
    {
        foreach (self::$bootstrappers as $bootstrapper) {
            // @phpstan-ignore-next-line
            (new $bootstrapper())->__invoke();
        }

        return new self(new Application());
    }

    /**
     * Handles the given argv.
     *
     * @param array<int, string> $argv
     */
    public function handle(array $argv): int
    {
        $argv = (new Plugins\Actions\HandleArguments())->__invoke($argv);

        $result = $this->application->run(
            $argv, false,
        );

        return (new Plugins\Actions\AddsOutput())->__invoke($result);
    }

    /**
     * Shutdown the Kernel.
     */
    public function shutdown(): void
    {
        // ..
    }
}
