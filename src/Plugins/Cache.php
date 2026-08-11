<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use PHPUnit\TextUI\CliArguments\Builder as CliConfigurationBuilder;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;
use PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration;
use PHPUnit\TextUI\XmlConfiguration\Loader;

/**
 * @internal
 */
final class Cache implements HandlesArguments
{
    use HandleArguments;

    private const string TEMPORARY_FOLDER = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'.temp';

    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--cache-directory', $arguments)) {

            $cliConfiguration = (new CliConfigurationBuilder)->fromParameters([]);
            $configurationFile = (new XmlConfigurationFileFinder)->find($cliConfiguration);
            $xmlConfiguration = DefaultConfiguration::create();

            if (is_string($configurationFile)) {
                $xmlConfiguration = (new Loader)->load($configurationFile);
            }

            if (! $xmlConfiguration->phpunit()->hasCacheDirectory()) {
                $arguments = $this->pushArgument('--cache-directory', $arguments);
                $arguments = $this->pushArgument((string) realpath(self::TEMPORARY_FOLDER), $arguments);
            }
        }

        if (! $this->hasArgument('--parallel', $arguments) && ! $this->hasTestRunHistoryArgument($arguments)) {
            return $this->pushArgument('--record-test-run-history', $arguments);
        }

        return $arguments;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function hasTestRunHistoryArgument(array $arguments): bool
    {
        return array_any(['--record-test-run-history', '--do-not-record-test-run-history', '--cache-result', '--do-not-cache-result'], fn (string $argument): bool => $this->hasArgument($argument, $arguments));
    }
}
