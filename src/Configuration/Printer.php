<?php

declare(strict_types=1);

namespace Pest\Configuration;

use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;

/**
 * @internal
 */
final readonly class Printer
{
    /**
     * Sets the theme to compact.
     */
    public function compact(): self
    {
        DefaultPrinter::compact(true);

        return $this;
    }
}
