<?php

declare(strict_types=1);

namespace Pest\Support;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

/**
 * @internal
 */
final class HigherOrderTapProxy
{
    private const UNDEFINED_PROPERTY = 'Undefined property: P\\';  // @phpstan-ignore-line

    /**
     * Create a new tap proxy instance.
     */
    public function __construct(
        public TestCase $target
    ) {
        // ..
    }

    /**
     * Dynamically sets properties on the target.
     *
     * @param  mixed  $value
     */
    public function __set(string $property, $value): void
    {
        $this->target->{$property} = $value; // @phpstan-ignore-line
    }

    /**
     * Dynamically pass properties gets to the target.
     *
     * @return mixed
     */
    public function __get(string $property)
    {
        try {
            return $this->target->{$property}; // @phpstan-ignore-line
        } catch (Throwable $throwable) {  // @phpstan-ignore-line
            Reflection::setPropertyValue($throwable, 'file', Backtrace::file());
            Reflection::setPropertyValue($throwable, 'line', Backtrace::line());

            if (Str::startsWith($message = $throwable->getMessage(), self::UNDEFINED_PROPERTY)) {
                /** @var ReflectionClass $reflection */
                $reflection = (new ReflectionClass($this->target))->getParentClass();
                Reflection::setPropertyValue($throwable, 'message', sprintf('Undefined property %s::$%s', $reflection->getName(), $property));
            }

            throw $throwable;
        }
    }

    /**
     * Dynamically pass method calls to the target.
     *
     * @param  array<int, mixed>  $arguments
     * @return mixed
     */
    public function __call(string $methodName, array $arguments)
    {
        $filename = Backtrace::file();
        $line = Backtrace::line();

        return (new HigherOrderMessage($filename, $line, $methodName, $arguments))
            ->call($this->target);
    }
}
