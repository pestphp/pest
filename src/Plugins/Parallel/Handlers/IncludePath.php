<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Parallel as ParallelPlugin;
use Pest\Plugins\Parallel\Contracts\HandlersWorkerArguments;
use Pest\Subscribers\EnsureCommandLineIncludePathComesFirst;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\TextUI\CliArguments\Builder;
use PHPUnit\TextUI\CliArguments\Configuration;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

final class IncludePath implements HandlersWorkerArguments, HandlesArguments
{
    private const string OPTION = '--include-path';

    private const string GLOBAL_KEY = 'INCLUDE_PATH';

    private const string PROGRAM = 'pest';

    public function handleArguments(array $arguments): array
    {
        $arguments = array_values($arguments);
        $includePath = null;
        $argumentsForParatest = [];
        $separatorAndArgumentsAfterIt = [];

        for ($index = 0; $index < count($arguments); $index++) {
            $argument = trim($arguments[$index]);

            if ($argument === '--') {
                $separatorAndArgumentsAfterIt = array_slice($arguments, $index);

                break;
            }

            if ($argument === self::OPTION || str_starts_with($argument, self::OPTION.'=')) {
                $optionArguments = $this->optionArgumentsPhpunitReads($arguments, $index);
                $includePath = $this->includePathPhpunitReads($optionArguments);
                $index += count($optionArguments) - 1;

                continue;
            }

            $argumentsForParatest[] = $arguments[$index];
        }

        ParallelPlugin::setGlobal(self::GLOBAL_KEY, $includePath === null ? false : base64_encode($includePath));

        if ($includePath !== null) {
            $commandLineIncludePathComesFirst = new EnsureCommandLineIncludePathComesFirst($includePath);
            $commandLineIncludePathComesFirst->apply();

            EventFacade::instance()->registerSubscriber($commandLineIncludePathComesFirst);
        }

        if ($includePath !== null && $this->survivesThePhpunitParser($includePath)) {
            array_push($argumentsForParatest, ...$this->argumentsPhpunitReadsAs($includePath));
        }

        return [...$argumentsForParatest, ...$separatorAndArgumentsAfterIt];
    }

    public static function configureOptions(InputDefinition $definition): void
    {
        $definition->addOption(new InputOption(substr(self::OPTION, 2), null, InputOption::VALUE_REQUIRED));
    }

    public function handleWorkerArguments(array $arguments): array
    {
        $encodedIncludePath = ParallelPlugin::getGlobal(self::GLOBAL_KEY);
        $includePath = is_string($encodedIncludePath) ? base64_decode($encodedIncludePath, true) : false;

        if (! is_string($includePath) || $includePath === '') {
            return $arguments;
        }

        return [...$arguments, ...$this->argumentsPhpunitReadsAs($includePath)];
    }

    /**
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function optionArgumentsPhpunitReads(array $arguments, int $index): array
    {
        if ($this->phpunitConfiguration([$arguments[$index]]) instanceof Configuration) {
            return [$arguments[$index]];
        }

        return array_slice($arguments, $index, 2);
    }

    /**
     * @param  list<string>  $optionArguments
     */
    private function includePathPhpunitReads(array $optionArguments): ?string
    {
        $configuration = $this->phpunitConfiguration($optionArguments);

        return $configuration instanceof Configuration && $configuration->hasIncludePath() ? $configuration->includePath() : null;
    }

    /**
     * @param  list<string>  $parameters
     */
    private function phpunitConfiguration(array $parameters): ?Configuration
    {
        try {
            return new Builder(EventFacade::emitter())->fromParameters([self::PROGRAM, ...$parameters]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function argumentsPhpunitReadsAs(string $includePath): array
    {
        if (! $this->survivesThePhpunitParser($includePath)) {
            return [self::OPTION, $includePath];
        }

        return [self::OPTION.'='.$includePath.$this->closingEqualsSignThatStopsTheTrim($includePath)];
    }

    private function closingEqualsSignThatStopsTheTrim(string $includePath): string
    {
        return rtrim($includePath) === $includePath ? '' : '=';
    }

    private function survivesThePhpunitParser(string $includePath): bool
    {
        return $includePath !== '' && ! str_contains($includePath, '=');
    }
}
