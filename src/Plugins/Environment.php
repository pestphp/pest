<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;

/**
 * @internal
 */
final class Environment implements HandlesArguments
{
    public const string CI = 'ci';

    public const string LOCAL = 'local';

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

    public static function name(?string $name = null): string
    {
        if (is_string($name)) {
            self::$name = $name;
        }

        return self::$name ?? self::LOCAL;
    }
}
