<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class TestClosureMustNotBeStatic extends \InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
}
