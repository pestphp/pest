<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use PHPUnit\TextUI\CliArguments\Builder;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;
use Throwable;

/**
 * @internal
 */
final readonly class ConfigurationFile
{
    /**
     * @param  array<int, string>  $arguments
     */
    public static function fromArguments(array $arguments): ?string
    {
        try {
            $configuration = new Builder()->fromParameters(self::configurationParameters($arguments));
        } catch (Throwable) {
            return null;
        }

        $file = new XmlConfigurationFileFinder()->find($configuration);

        if ($file === false) {
            return null;
        }

        $path = realpath($file);

        return $path === false ? null : $path;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return list<string>
     */
    private static function configurationParameters(array $arguments): array
    {
        $arguments = array_values($arguments);
        $parameters = [];

        foreach ($arguments as $index => $argument) {
            if ($argument === '--no-configuration') {
                $parameters[] = $argument;
            } elseif ($argument === '--configuration' || $argument === '-c') {
                if (isset($arguments[$index + 1])) {
                    $parameters[] = '--configuration';
                    $parameters[] = $arguments[$index + 1];
                }
            } elseif (str_starts_with($argument, '--configuration=')) {
                $parameters[] = '--configuration';
                $parameters[] = substr($argument, strlen('--configuration='));
            } elseif (str_starts_with($argument, '-c') && ! str_starts_with($argument, '--')) {
                $parameters[] = '--configuration';
                $parameters[] = substr($argument, 2);
            }
        }

        return $parameters;
    }
}
