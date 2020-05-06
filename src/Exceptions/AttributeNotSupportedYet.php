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
final class AttributeNotSupportedYet extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    /**
     * Creates a new instance of attribute not supported yet.
     */
    public function __construct(string $attribute, string $value)
    {
        parent::__construct(sprintf('The PHPUnit attribute `%s` with value `%s` is not supported yet.', $attribute, $value));
    }
}
