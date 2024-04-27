<?php

declare(strict_types=1);

namespace Pest\Factories;

/**
 * @internal
 */
final class Attribute
{
    /**
     * @param  iterable<int, string>  $arguments
     */
    public function __construct(public string $name, public iterable $arguments)
    {
        //
    }
}
