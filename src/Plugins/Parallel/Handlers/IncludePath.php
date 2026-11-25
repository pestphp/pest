<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Parallel as ParallelPlugin;
use Pest\Plugins\Parallel\Contracts\HandlersWorkerArguments;
use Pest\Subscribers\EnsureIncludePathIsPrepended;
use PHPUnit\Event\Facade as EventFacade;

final class IncludePath implements HandlersWorkerArguments, HandlesArguments
{
    private const string OPTION = '--include-path';

    private const string GLOBAL_KEY = 'INCLUDE_PATH';

    public function handleArguments(array $arguments): array
    {
        $arguments = array_values($arguments);
        $includePath = null;
        $argumentsForParatest = [];

        for ($index = 0; $index < count($arguments); $index++) {
            $argument = $arguments[$index];

            if ($argument === '--') {
                array_push($argumentsForParatest, ...array_slice($arguments, $index));

                break;
            }

            if (str_starts_with($argument, self::OPTION.'=')) {
                $includePath = substr($argument, strlen(self::OPTION) + 1);

                continue;
            }

            if ($argument === self::OPTION) {
                $next = $arguments[$index + 1] ?? null;

                if ($next !== null && ! str_starts_with($next, '-')) {
                    $includePath = $next;
                    $index++;
                }

                continue;
            }

            $argumentsForParatest[] = $argument;
        }

        ParallelPlugin::setGlobal(self::GLOBAL_KEY, $includePath === null ? false : base64_encode($includePath));

        if ($includePath !== null) {
            EventFacade::instance()->registerSubscriber(new EnsureIncludePathIsPrepended($includePath));
        }

        return $argumentsForParatest;
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
}
