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
final class TestCaseAlreadyInUse extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    public function __construct(string $inUse, string $newOne, string $folder)
    {
        parent::__construct(sprintf(
            'The test case [%s] may not be used here. The folder [%s] is already bound to the test case [%s].',
            $newOne,
            $folder,
            $inUse,
        ));
    }
}
