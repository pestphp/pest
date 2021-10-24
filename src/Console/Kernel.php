<?php

declare(strict_types=1);

namespace Pest\Console;

use Pest\Actions\InteractsWithPlugins;
use Pest\Bootstrappers;
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
        Bootstrappers\BootEmitter::class,
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
        $argv = InteractsWithPlugins::handleArguments($argv);

        $result = $this->application->run(
            $argv, false,
        );

        return InteractsWithPlugins::addOutput($result);
    }

    /**
     * Shutdown the Kernel.
     */
    public function shutdown(): void
    {
        // TODO
    }
}
