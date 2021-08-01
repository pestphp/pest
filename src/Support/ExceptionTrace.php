<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use ReflectionProperty;
use Throwable;

/**
 * @internal
 */
final class ExceptionTrace
{
    private const UNDEFINED_METHOD = 'Call to undefined method P\\';

    /**
     * Ensures the given closure reports
     * the good execution context.
     *
     * @return mixed
     *
     * @throws Throwable
     */
    public static function ensure(Closure $closure)
    {
        try {
            return $closure();
        } catch (Throwable $throwable) {
            if (Str::startsWith($message = $throwable->getMessage(), self::UNDEFINED_METHOD)) {
                $message = str_replace(self::UNDEFINED_METHOD, 'Call to undefined method ', $message);

                Reflection::setPropertyValue($throwable, 'message', $message);
            }

            throw $throwable;
        }
    }

    /**
     * Removes any item from the stack trace referencing Pest so as not to
     * crowd the error log for the end user.
     */
    public static function removePestReferences(Throwable $t): void
    {
        if (!property_exists($t, 'serializableTrace')) {
            return;
        }

        $property = new ReflectionProperty($t, 'serializableTrace');
        $property->setAccessible(true);
        $trace = $property->getValue($t);

        $cleanedTrace = [];
        foreach ($trace as $item) {
            if (key_exists('file', $item) && mb_strpos($item['file'], 'vendor/pestphp/pest/') > 0) {
                continue;
            }

            $cleanedTrace[] = $item;
        }

        $property->setValue($t, $cleanedTrace);
    }
}
