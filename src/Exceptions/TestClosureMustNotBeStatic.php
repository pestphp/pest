<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;
use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Pest\Factories\TestCaseMethodFactory;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class TestClosureMustNotBeStatic extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    public function __construct(TestCaseMethodFactory $method)
    {
        parent::__construct(
            sprintf(
                'Test closures may not be static. Please remove the [static] keyword from the test [%s] in [%s].',
                $method->description,
                $method->filename
            )
        );
    }
}
