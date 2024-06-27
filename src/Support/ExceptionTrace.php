<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @internal
 */
final class ExceptionTrace
{
    private const UNDEFINED_METHOD = 'Call to undefined method P\\';

    /**
     * Ensures the given closure reports the good execution context.
     *
     * @throws Throwable
     */
    public static function ensure(Closure $closure): mixed
    {
        try {
            return $closure();
        } catch (Throwable $throwable) {
            if (Str::startsWith($message = $throwable->getMessage(), self::UNDEFINED_METHOD)) {
                $class = preg_match('/^Call to undefined method ([^:]+)::/', $message, $matches) === false ? null : $matches[1];

                $message = str_replace(self::UNDEFINED_METHOD, 'Call to undefined method ', $message);

                if (class_exists((string) $class) && (is_countable(class_parents($class)) ? count(class_parents($class)) : 0) > 0 && array_values(class_parents($class))[0] === TestCase::class) { // @phpstan-ignore-line
                    $message .= '. Did you forget to use the [pest()->extend()] function? Read more at: https://pestphp.com/docs/configuring-tests';
                }

                Reflection::setPropertyValue($throwable, 'message', $message);
            }

            throw $throwable;
        }
    }
}
