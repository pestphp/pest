<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use BadFunctionCallException;
use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * Creates a new instance of dataset is not present for test that has arguments.
 *
 * @internal
 */
final class DatasetMissing extends BadFunctionCallException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    /**
     * Create new exception instance.
     *
     * @param array<string, string> $args A map of argument names to their typee
     */
    public function __construct(string $file, string $name, array $args)
    {
        parent::__construct(sprintf(
            "A test with the description '%s' has %d argument(s) ([%s]) and no dataset(s) provided in %s",
            $name,
            count($args),
            implode(', ', array_map(static function (string $arg, string $type): string {
                return sprintf('%s $%s', $type, $arg);
            }, array_keys($args), $args)),
            $file,
        ));
    }
}
