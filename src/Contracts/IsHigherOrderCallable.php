<?php

declare(strict_types=1);

namespace Pest\Contracts;

/**
 * Interface used to mark a class callable by higher order expectations.
 */
interface IsHigherOrderCallable
{
    public function __invoke(): mixed;
}
