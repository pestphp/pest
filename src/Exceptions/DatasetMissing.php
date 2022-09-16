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
     * Creates a new Exception instance.
     *
     * @param  array<string, string>  $arguments
     */
    public function __construct(string $file, string $name, array $arguments)
    {
        parent::__construct(sprintf(
            "A test with the description '%s' has %d argument(s) ([%s]) and no dataset(s) provided in %s",
            $name,
            count($arguments),
            implode(', ', array_map(static fn (string $arg, string $type): string => sprintf('%s $%s', $type, $arg), array_keys($arguments), $arguments)),
            $file,
        ));
    }
}
