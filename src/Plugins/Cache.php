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

    private const TEMPORARY_FOLDER = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'.temp';

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $arguments): array
    {
        $arguments = $this->pushArgument(
            sprintf('--cache-directory=%s', realpath(self::TEMPORARY_FOLDER)),
            $arguments
        );

        return $this->pushArgument('--cache-result', $arguments);
    }
}