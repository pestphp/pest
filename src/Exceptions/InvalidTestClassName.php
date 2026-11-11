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
final class InvalidTestClassName extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    public static function fromClassName(string $filename, string $className): self
    {
        return new self(sprintf(
            'The test file [%s] would create the class [%s], which is not a valid PHP class name. Please rename the file.',
            $filename,
            $className,
        ));
    }

    public static function fromNamespace(string $filename, string $namespace, string $part): self
    {
        return new self(sprintf(
            'The test file [%s] would create the namespace [%s], which is not a valid PHP namespace, because [%s] may not be used as a namespace name. Please rename that folder.',
            $filename,
            $namespace,
            $part,
        ));
    }
}
