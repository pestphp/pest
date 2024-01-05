<?php

declare(strict_types=1);

namespace Pest\Evaluators;

use Pest\Factories\Attribute;

/**
 * @internal
 */
final class Attributes
{
    /**
     * Evaluates the given attributes and returns the code.
     */
    public static function code(iterable $attributes): string
    {
        return implode(PHP_EOL, array_map(function (Attribute $attribute): string {
            $name = $attribute->name;

            if ($attribute->arguments === []) {
                return "    #[\\{$name}]";
            }

            $arguments = array_map(fn (string $argument): string => var_export($argument, true), $attribute->arguments);

            return sprintf('    #[\\%s(%s)]', $name, implode(', ', $arguments));
        }, $attributes));
    }
}
