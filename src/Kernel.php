<?php

declare(strict_types=1);

namespace Pest;

use PHPUnit\TestRunner\TestResult\Facade;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Application;
use PHPUnit\TextUI\Configuration\Registry;

/**
 * @internal
 */
final class Kernel
{
    private const SUCCESS_EXIT = 0;

    private const FAILURE_EXIT = 1;

    private const EXCEPTION_EXIT = 2;

    private const CRASH_EXIT = 255;

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

        $this->application->run(
            $argv, false,
        );

        $returnCode = $this->returnCode();

        return (new Plugins\Actions\AddsOutput())->__invoke(
            $returnCode,
        );
    }

    /**
     * Shutdown the Kernel.
     */
    public function shutdown(): void
    {
        // ..
    }

    /**
     * Returns the exit code, based on the facade's result.
     */
    private function returnCode(): int
    {
        $result = Facade::result();

        $returnCode = self::FAILURE_EXIT;

        if ($result->wasSuccessfulIgnoringPhpunitWarnings()
            && ! $result->hasTestTriggeredPhpunitWarningEvents()) {
            $returnCode = self::SUCCESS_EXIT;
        }

        $configuration = Registry::get();

        if ($configuration->failOnEmptyTestSuite() && $result->numberOfTests() === 0) {
            $returnCode = self::FAILURE_EXIT;
        }

        if ($result->wasSuccessfulIgnoringPhpunitWarnings()) {
            if ($configuration->failOnRisky() && $result->hasTestConsideredRiskyEvents()) {
                $returnCode = self::FAILURE_EXIT;
            }

            $warnings = $result->numberOfTestsWithTestTriggeredPhpunitWarningEvents()
                + $result->numberOfTestsWithTestTriggeredWarningEvents()
                + $result->numberOfTestsWithTestTriggeredPhpWarningEvents();

            if ($configuration->failOnWarning() && ! empty($warnings)) {
                $returnCode = self::FAILURE_EXIT;
            }

            if ($configuration->failOnIncomplete() && $result->hasTestMarkedIncompleteEvents()) {
                $returnCode = self::FAILURE_EXIT;
            }


            if ($configuration->failOnSkipped() && $result->hasTestSkippedEvents()) {
                $returnCode = self::FAILURE_EXIT;
            }

        }

        if ($result->hasTestErroredEvents()) {
            $returnCode = self::EXCEPTION_EXIT;
        }

        return $returnCode;
    }
}
