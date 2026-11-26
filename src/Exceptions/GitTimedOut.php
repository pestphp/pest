<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use RuntimeException;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class GitTimedOut extends RuntimeException implements ExceptionInterface, RenderlessEditor, RenderlessTrace
{
    /**
     * @param  array<int, string>  $arguments
     */
    public function __construct(array $arguments, float $timeout)
    {
        parent::__construct(sprintf('Git did not answer within %s seconds: git %s', $timeout, implode(' ', $arguments)));
    }
}
