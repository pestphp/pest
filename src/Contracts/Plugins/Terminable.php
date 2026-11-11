<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface Terminable
{
    public function terminate(): void;
}
