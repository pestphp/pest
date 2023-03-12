<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;

/**
 * @internal
 */
final class Cache implements HandlesArguments
{
    use HandleArguments;

    /**
     * The temporary folder.
     */
    private const TEMPORARY_FOLDER = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'.temp';

    /**
     * Handles the arguments, adding the cache directory and the cache result arguments.
     */
    public function handleArguments(array $arguments): array
    {
        $arguments = $this->pushArgument(
            sprintf('--cache-directory=%s', realpath(self::TEMPORARY_FOLDER)),
            $arguments
        );

        if (! $this->hasArgument('--parallel', $arguments)) {
            return $this->pushArgument('--cache-result', $arguments);
        }

        return $arguments;
    }
}
