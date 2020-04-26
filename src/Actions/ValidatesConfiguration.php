<?php

declare(strict_types=1);

namespace Pest\Actions;

use Pest\Exceptions\AttributeNotSupportedYet;
use Pest\Exceptions\FileNotFound;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TextUI\Configuration\Registry;

/**
 * @internal
 */
final class ValidatesConfiguration
{
    /**
     * Validates the configuration in the given `configuration`.
     *
     * @param array<string, mixed> $arguments
     */
    public static function in($arguments): void
    {
        if (!array_key_exists('configuration', $arguments) || !file_exists($arguments['configuration'])) {
            throw new FileNotFound('phpunit.xml');
        }

        $configuration = Registry::getInstance()
            ->get($arguments['configuration'])
            ->phpunit();

        if ($configuration->processIsolation()) {
            throw new AttributeNotSupportedYet('processIsolation', 'true');
        }
    }
}
