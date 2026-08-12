<?php

declare(strict_types=1);

namespace Pest\Configuration;

use Closure;
use Pest\Preset;

final class Presets
{
    public function custom(string $name, Closure $execute): void
    {
        Preset::custom($name, $execute);
    }
}
