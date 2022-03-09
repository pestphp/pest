<?php

declare(strict_types=1);

namespace Pest\Factories\Attributes;

/**
 * @internal
 */
abstract class Attribute
{
    /**
     * Determine if the attribute should be placed above the class instead of above the method.
     *
     * @var bool
     */
    public const ABOVE_CLASS = false;
}
