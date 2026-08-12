<?php

declare(strict_types=1);

namespace Pest\PendingCalls\Concerns;

use Pest\Support\Description;

/**
 * @internal
 */
trait Describable
{
    /**
     * @var array<int, Description>
     */
    public array $__describing;

    /**
     * @var array<int, Description>
     */
    public array $describing = [];
}
