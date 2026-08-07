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
final class TestCaseClassOrTraitNotFound extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    public function __construct(string $testCaseClass)
    {
        parent::__construct(sprintf('The class or trait [%s] could not be found. Please check the name, and make sure it is autoloadable.', $testCaseClass));
    }
}
