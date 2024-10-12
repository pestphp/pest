<?php

declare(strict_types=1);

namespace Pest\PendingCalls\Concerns;

/**
 * @internal
 */
trait Describable
{
    /**
     * Note: this is property is not used; however, it gets added automatically by rector php.
     *
     * @var array<int, \Pest\Support\Description>
     */
    public array $__describing;

    /**
     * The describing of the test case.
     *
     * @var array<int, \Pest\Support\Description>
     */
    public array $describing = [];
}
