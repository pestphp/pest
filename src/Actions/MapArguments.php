<?php

declare(strict_types=1);

namespace Pest\Actions;

use Pest\Console\Paratest\Runner;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugin\Loader;
use Pest\Support\Container;
use Pest\Support\Coverage;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

final class MapArguments
{
    public static function toParatest(TestSuite $testSuite): void
    {
        self::registerPlugins();
        self::coverage();
        self::parallel();
        self::color();
    }

    public static function toPest(TestSuite $testSuite): void
    {
        self::inParallel($testSuite);
        // we could add coverage here too, so we stop before even running tests if there is no coverage driver
    }

    private static function registerPlugins(): void
    {
        $plugins = Loader::getPlugins(HandlesArguments::class);

        /** @var HandlesArguments $plugin */
        foreach ($plugins as $plugin) {
            $_SERVER['argv'] = $plugin->handleArguments($_SERVER['argv']);
        }
    }

    private static function parallel(): void
    {
        if (self::unsetArgument('--parallel')) {
            self::setArgument('--runner', Runner::class);
        }
    }

    private static function inParallel(TestSuite $testSuite): void
    {
        if (self::unsetArgument('--isInParallel')) {
            $testSuite->isInParallel = true;
        }
    }

    private static function color(): void
    {
        $argv = new ArgvInput();
        $isDecorated = $argv->getParameterOption('--colors', 'always') !== 'never';

        self::unsetArgument('--colors');
        //refactor later
        self::unsetArgument('--colors=always');
        self::unsetArgument('--colors=auto');
        self::unsetArgument('--colors=never');

        if ($isDecorated) {
            self::setArgument('--colors');
        }
    }

    private static function coverage(): void
    {
        if (self::needsCoverage() && ! Coverage::isAvailable()) {
            Container::getInstance()->get(OutputInterface::class)->writeln(
                "\n  <fg=white;bg=red;options=bold> ERROR </> No code coverage driver is available.</>",
            );
            exit(1);
        }
    }

    private static function needsCoverage(): bool
    {
        foreach ($_SERVER['argv'] as $argument) {
            if(str_starts_with($argument, '--coverage')) {
                return true;
            }
        }

        return false;
    }

    private static function unsetArgument(string $argument): bool
    {
        if (($key = array_search($argument, $_SERVER['argv'])) !== false) {
            unset($_SERVER['argv'][$key]);

            return true;
        }

        return false;
    }

    private static function setArgument(string $argument, string $value = null): void
    {
        $_SERVER['argv'][] = $argument;

        if ($value !== null) {
            $_SERVER['argv'][] = $value;
        }
    }
}
