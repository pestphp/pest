<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use LogicException;
use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class InvalidExpectation extends LogicException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    /**
     * @param  array<int, string>  $methods
     *
     * @throws self
     */
    public static function fromMethods(array $methods): never
    {
        throw new self(sprintf('Expectation [%s] is not valid.', implode('->', $methods)));
    }
}
