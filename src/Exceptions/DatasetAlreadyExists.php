<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;
use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class DatasetAlreadyExists extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    public function __construct(string $name, string $scope)
    {
        parent::__construct(sprintf('A dataset named [%s] is already registered in [%s]. Please choose a different name.', $name, $scope));
    }
}
