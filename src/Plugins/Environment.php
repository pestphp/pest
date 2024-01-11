<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;

/**
 * @internal
 */
final class Environment implements HandlesArguments
{
    /**
     * The continuous integration environment.
     */
    public const CI = 'ci';

    /**
     * The local environment.
     */
    public const LOCAL = 'local';

    /**
     * The current environment.
     */
    private static ?string $name = null;

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $arguments): array
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === '--ci') {
                unset($arguments[$index]);

                self::$name = self::CI;
            }
        }

        return array_values($arguments);
    }

    /**
     * Gets the environment name.
     */
    public static function name(?string $name = null): string
    {
        if (is_string($name)) {
            self::$name = $name;
        }

        return self::$name ?? self::LOCAL;
    }
}
