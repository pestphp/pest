<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class AttributeNotSupportedYet extends InvalidArgumentException
{
    /**
     * Creates a new instance of attribute not supported yet.
     */
    public function __construct(string $attribute, string $value)
    {
        parent::__construct(sprintf('The PHPUnit attribute `%s` with value `%s` is not supported yet.', $attribute, $value));
    }
}
