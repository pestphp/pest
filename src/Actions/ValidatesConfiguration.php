<?php

declare(strict_types=1);

namespace Pest\Actions;

use Pest\Exceptions\AttributeNotSupportedYet;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TextUI\Configuration\PHPUnit;

/**
 * @internal
 */
final class ValidatesConfiguration
{
    /**
     * Validates the configuration in the given `configuration`.
     *
     * @param PHPUnit $configuration
     */
    public static function in($configuration): void
    {
        if ($configuration->processIsolation()) {
            throw new AttributeNotSupportedYet('processIsolation', 'true');
        }
    }
}
