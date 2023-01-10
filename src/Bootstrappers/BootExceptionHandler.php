<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use NunoMaduro\Collision;
use Pest\Contracts\Bootstrapper;

/**
 * @internal
 */
final class BootExceptionHandler implements Bootstrapper
{
    /**
     * Boots the Exception Handler.
     */
    public function boot(): void
    {
        $handler = new Collision\Provider();

        $handler->register();
    }
}
