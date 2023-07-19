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
    /**
     * Creates a new Exception instance.
     */
    public function __construct(string $filename)
    {
        parent::__construct(sprintf('The afterAll method can not be used within describe functions. Filename `%s`.', $filename));
    }
}
