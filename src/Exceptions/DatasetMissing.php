<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use BadFunctionCallException;
use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class DatasetMissing extends BadFunctionCallException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    /**
     * @param  array<string, string>  $arguments
     */
    public function __construct(string $file, string $name, array $arguments)
    {
        parent::__construct(sprintf(
            'The test [%s] in [%s] expects [%d] argument(s) ([%s]), but no dataset was provided. Please chain [with()] onto the test to supply one.',
            $name,
            $file,
            count($arguments),
            implode(', ', array_map(static fn (string $arg, string $type): string => sprintf('%s $%s', $type, $arg), array_keys($arguments), $arguments)),
        ));
    }
}
