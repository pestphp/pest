<?php

declare(strict_types=1);

namespace Pest\Actions;

use NunoMaduro\Collision\Adapters\Phpunit\Printer;
use Pest\TeamCity;
use PHPUnit\TextUI\DefaultResultPrinter;

/**
 * @internal
 */
final class AddsDefaults
{
    private const PRINTER = 'printer';

    /**
     * Adds default arguments to the given `arguments` array.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public static function to(array $arguments): array
    {
        if (!array_key_exists(self::PRINTER, $arguments)) {
            $arguments[self::PRINTER] = new Printer(null, $arguments['verbose'] ?? false, $arguments['colors'] ?? DefaultResultPrinter::COLOR_ALWAYS);
        }

        if ($arguments[self::PRINTER] === \PHPUnit\Util\Log\TeamCity::class) {
            $arguments[self::PRINTER] = new TeamCity($arguments['verbose'] ?? false, $arguments['colors'] ?? DefaultResultPrinter::COLOR_ALWAYS);
        }

        return $arguments;
    }
}
