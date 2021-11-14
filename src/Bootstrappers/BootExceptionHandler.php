<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use NunoMaduro\Collision;

/**
 * @internal
 */
final class BootExceptionHandler
{
    /**
     * Boots the Exception Handler.
     */
    public function __invoke(): void
    {
        $handler = new Collision\Provider();

        $handler->register();
    }
}
