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
final class AfterAllWithinDescribe extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    public function __construct(string $filename)
    {
        parent::__construct(sprintf('The [afterAll] hook may not be used inside a [describe] block. Please move it to the top level of [%s].', $filename));
    }
}
