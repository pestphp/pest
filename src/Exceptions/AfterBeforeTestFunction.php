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
final class AfterBeforeTestFunction extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    public function __construct(string $filename)
    {
        parent::__construct(sprintf('The [after] hook may only be chained onto [beforeEach] inside a [describe] block. Please move it inside one in [%s].', $filename));
    }
}
