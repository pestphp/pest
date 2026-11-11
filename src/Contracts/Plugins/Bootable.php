<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface Bootable
{
    public function boot(): void;
}
