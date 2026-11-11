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
final class DatasetDoesNotExist extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf("A dataset named [%s] does not exist. You may create one using `dataset('%s', ['a', 'b']);`.", $name, $name));
    }
}
