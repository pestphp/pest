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
final class TestDescriptionMissing extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    public function __construct(string $fileName)
    {
        parent::__construct(sprintf('A test in [%s] is missing its description. Please give every test a description.', $fileName));
    }
}
