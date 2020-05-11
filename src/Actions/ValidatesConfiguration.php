<?php

declare(strict_types=1);

namespace Pest\Actions;

use Pest\Exceptions\AttributeNotSupportedYet;
use Pest\Exceptions\FileOrFolderNotFound;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TextUI\Configuration\Registry;

/**
 * @internal
 */
final class ValidatesConfiguration
{
    /**
     * @var string
     */
    private const CONFIGURATION_KEY = 'configuration';

    /**
     * Validates the configuration in the given `configuration`.
     *
     * @param array<string, mixed> $arguments
     */
    public static function in($arguments): void
    {
        if (!array_key_exists(self::CONFIGURATION_KEY, $arguments) || !file_exists($arguments[self::CONFIGURATION_KEY])) {
            throw new FileOrFolderNotFound('phpunit.xml');
        }

        $configuration = Registry::getInstance()
            ->get($arguments[self::CONFIGURATION_KEY])
            ->phpunit();

        if ($configuration->processIsolation()) {
            throw new AttributeNotSupportedYet('processIsolation', 'true');
        }
    }
}
