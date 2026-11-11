<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Parallel\Contracts\HandlersWorkerArguments;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

final class StopOn implements HandlersWorkerArguments, HandlesArguments
{
    private const array OPTIONS = [
        '--stop-on-defect',
        '--stop-on-error',
        '--stop-on-failure',
        '--stop-on-warning',
        '--stop-on-risky',
        '--stop-on-deprecation',
        '--stop-on-notice',
        '--stop-on-skipped',
        '--stop-on-incomplete',
    ];

    public function handleArguments(array $arguments): array
    {
        foreach ($arguments as $index => $argument) {
            [$option, $value] = array_pad(explode('=', $argument, 2), 2, '');

            if (in_array($option, self::OPTIONS, true)
                && in_array($value, ['', '0'], true)) {
                $arguments[$index] = $option.'=1';
            }
        }

        return $arguments;
    }

    public function handleWorkerArguments(array $arguments): array
    {
        $arguments = array_values($arguments);

        foreach ($arguments as $index => $argument) {
            if (! in_array($argument, self::OPTIONS, true)
                || ! isset($arguments[$index + 1])
                || str_starts_with($arguments[$index + 1], '--')) {
                continue;
            }

            $arguments[$index] .= '='.$arguments[$index + 1];
            unset($arguments[$index + 1]);
        }

        return array_values($arguments);
    }

    public static function configureOptions(InputDefinition $definition): void
    {
        $options = $definition->getOptions();

        foreach (self::OPTIONS as $argument) {
            $name = substr($argument, 2);
            $option = $options[$name];
            $options[$name] = new InputOption(
                $name,
                $option->getShortcut(),
                InputOption::VALUE_OPTIONAL,
                $option->getDescription(),
                false,
            );
        }

        $definition->setOptions($options);
    }
}
