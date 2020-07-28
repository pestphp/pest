<?php

declare(strict_types=1);

namespace Pest\Actions;

use NunoMaduro\Collision\Adapters\Phpunit\Printer;
use Pest\TeamCity;

/**
 * @internal
 */
final class AddsDefaults
{
    /**
     * Adds default arguments to the given `arguments` array.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public static function to(array $arguments): array
    {
        if (!array_key_exists('printer', $arguments)) {
            $arguments['printer'] = new Printer(null, $arguments['verbose'] ?? false, $arguments['colors'] ?? 'always');
        }

        if ($arguments['printer'] === \PHPUnit\Util\Log\TeamCity::class) {
            $arguments['printer'] = new TeamCity(null, $arguments['verbose'] ?? false, $arguments['colors'] ?? 'always');
        }

        return $arguments;
    }
}
