<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Parallel as ParallelPlugin;
use Pest\Plugins\Parallel\Contracts\HandlersWorkerArguments;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

final class IncludePath implements HandlersWorkerArguments, HandlesArguments
{
    private const string OPTION = '--include-path';

    private const string GLOBAL_KEY = 'INCLUDE_PATH';

    public function handleArguments(array $arguments): array
    {
        $includePath = $this->includePathFrom($arguments);

        ParallelPlugin::setGlobal(self::GLOBAL_KEY, $includePath === null ? false : base64_encode($includePath));

        return $arguments;
    }

    public function handleWorkerArguments(array $arguments): array
    {
        $encodedIncludePath = ParallelPlugin::getGlobal(self::GLOBAL_KEY);
        $includePath = is_string($encodedIncludePath) ? base64_decode($encodedIncludePath, true) : false;

        if (! is_string($includePath) || $includePath === '') {
            return $arguments;
        }

        return [...$arguments, self::OPTION, $includePath];
    }

    public static function configureOptions(InputDefinition $definition): void
    {
        $definition->addOption(new InputOption(
            substr(self::OPTION, 2),
            null,
            InputOption::VALUE_REQUIRED,
            'Prepend PHP\'s include_path with given path(s)',
        ));
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function includePathFrom(array $arguments): ?string
    {
        $arguments = array_values($arguments);
        $includePath = null;

        foreach ($arguments as $index => $argument) {
            if ($argument === '--') {
                break;
            }

            if (str_starts_with($argument, self::OPTION.'=')) {
                $includePath = substr($argument, strlen(self::OPTION) + 1);

                continue;
            }

            $next = $arguments[$index + 1] ?? null;

            if ($argument === self::OPTION && $next !== null && ! str_starts_with($next, '-')) {
                $includePath = $next;
            }
        }

        return $includePath;
    }
}
