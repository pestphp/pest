<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Contracts\Bootstrapper;
use PHPUnit\TextUI\Configuration\Builder;

/**
 * @internal
 */
final class BootPhpUnitConfiguration implements Bootstrapper
{
    public function boot(): void
    {
        (new Builder)->build(['pest']);
    }
}
