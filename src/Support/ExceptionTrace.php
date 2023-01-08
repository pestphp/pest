<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
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
}
